<?php
/**
 * Config File Updater
 * Properly updates config.php with setup wizard values
 * Updates ALL instances of variables (both early return and main section)
 * Includes syntax validation to prevent corruption
 */

class ConfigUpdater {
    private $config_file;
    private $values;
    
    public function __construct($config_file) {
        $this->config_file = $config_file;
        $this->values = [];
    }
    
    /**
     * Set a configuration value
     */
    public function set($key, $value) {
        $this->values[$key] = $value;
    }
    
    /**
     * Update config.php with all set values
     * Updates ALL instances of each variable (both early return and main section)
     */
    public function update() {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config file not found: " . $this->config_file);
        }
        
        if (!is_readable($this->config_file)) {
            throw new Exception("Config file not readable: " . $this->config_file);
        }
        
        if (!is_writable($this->config_file)) {
            $real_path = realpath($this->config_file);
            @chmod($real_path, 0664);
            
            if (!is_writable($real_path)) {
                $web_user = 'www-data';
                if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                    $process_user = posix_getpwuid(posix_geteuid());
                    $web_user = $process_user['name'] ?? 'www-data';
                }
                @exec("chown {$web_user}:{$web_user} " . escapeshellarg($real_path) . " 2>&1", $chown_out, $chown_code);
                @chmod($real_path, 0664);
                
                if (!is_writable($real_path)) {
                    throw new Exception("Config file not writable: " . $this->config_file . ". Please run as root: chown www-data:www-data " . $real_path . " && chmod 664 " . $real_path);
                }
            }
        }
        
        // Read original content
        $original_content = file_get_contents($this->config_file);
        $content = $original_content;
        
        // Update each value - replace ALL instances (both early return and main section)
        foreach ($this->values as $var_name => $value) {
            $escaped_value = addslashes($value);
            
            // Pattern to match: $VARNAME = 'anything'; (matches both single and double quotes)
            // This will match ALL instances in the file
            $pattern = "/(\\\${$var_name}\s*=\s*)(['\"])([^'\"]*)(\\2;)/";
            
            // Replacement: keep the variable declaration, replace only the value
            $replacement = '${1}' . "'" . $escaped_value . "';";
            
            // Perform replacement (this replaces ALL matches)
            $new_content = preg_replace($pattern, $replacement, $content);
            
            if ($new_content === null) {
                throw new Exception("Regex error while updating {$var_name}");
            }
            
            // If no change, try alternative pattern (for different quote styles)
            if ($new_content === $content) {
                // Try with different quote handling
                $alt_pattern = "/(\\\${$var_name}\s*=\s*['\"])([^'\"]*)(['\"];)/";
                $alt_replacement = '${1}' . $escaped_value . '${3}';
                $new_content = preg_replace($alt_pattern, $alt_replacement, $content);
                
                if ($new_content === null) {
                    throw new Exception("Regex error (alt pattern) while updating {$var_name}");
                }
            }
            
            $content = $new_content;
        }
        
        // Validate PHP syntax before writing
        $temp_file = $this->config_file . '.tmp';
        file_put_contents($temp_file, $content);
        
        // Check syntax using PHP lint
        $output = [];
        $return_code = 0;
        @exec("php -l " . escapeshellarg($temp_file) . " 2>&1", $output, $return_code);
        
        if ($return_code !== 0) {
            @unlink($temp_file);
            $error_msg = "Syntax error in updated config.php: " . implode("\n", $output);
            error_log($error_msg);
            throw new Exception("Failed to update config.php - syntax error detected. Original file preserved.");
        }
        
        // Syntax is valid - replace original file
        if (!rename($temp_file, $this->config_file)) {
            @unlink($temp_file);
            throw new Exception("Failed to replace config.php");
        }
        
        // Verify updates - check that at least the main section was updated
        $this->verify();
        
        return true;
    }
    
    /**
     * Verify that all values were updated correctly
     * Checks the main config section (after line 80)
     */
    private function verify() {
        $content = file_get_contents($this->config_file);
        $lines = explode("\n", $content);
        
        // Find the main config section (after database connection, around line 84)
        $main_section_start = false;
        foreach ($lines as $i => $line) {
            if (strpos($line, '$pdo = new PDO') !== false) {
                $main_section_start = $i;
                break;
            }
        }
        
        foreach ($this->values as $var_name => $value) {
            // Check if the value appears in the main section
            $found = false;
            if ($main_section_start !== false) {
                for ($i = $main_section_start; $i < count($lines) && $i < $main_section_start + 20; $i++) {
                    if (strpos($lines[$i], '$' . $var_name) !== false && strpos($lines[$i], $value) !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            
            // Also check with regex as fallback
            if (!$found) {
                $pattern = "/\\\${$var_name}\s*=\s*['\"]" . preg_quote($value, '/') . "['\"];/";
                if (preg_match($pattern, $content)) {
                    $found = true;
                }
            }
            
            if (!$found) {
                error_log("Warning: Config update verification failed for {$var_name} - value not found in main section");
            }
        }
    }
    
    /**
     * Restart the bot after config update
     */
    public function restartBot() {
        if (function_exists('exec')) {
            @exec('supervisorctl stop mirza_bot 2>&1', $stop_out, $stop_code);
            sleep(1);
            @exec('supervisorctl start mirza_bot 2>&1', $start_out, $start_code);
            sleep(2);
            
            // Verify bot started
            @exec('supervisorctl status mirza_bot 2>&1', $status_out, $status_code);
            if (!empty($status_out[0]) && strpos($status_out[0], 'RUNNING') === false) {
                error_log("Warning: Bot may not have started after config update");
                return false;
            }
            return true;
        }
        return false;
    }
}
