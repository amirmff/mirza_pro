<?php
/**
 * Config File Updater
 * Properly updates config.php with setup wizard values
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
     */
    public function update() {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config file not found: " . $this->config_file);
        }
        
        if (!is_readable($this->config_file)) {
            throw new Exception("Config file not readable: " . $this->config_file);
        }
        
        if (!is_writable($this->config_file)) {
            // Try to fix permissions automatically
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
        
        // Update each value with careful replacement
        foreach ($this->values as $var_name => $value) {
            $escaped_value = addslashes($value);
            
            // Pattern to match: $VARNAME = 'anything';
            // Capture groups: 1=variable and equals, 2=quote, 3=value, 4=quote and semicolon
            $pattern = "/(\\\${$var_name}\s*=\s*)(['\"])([^'\"]*)(\\2;)/";
            
            // Build replacement string carefully
            $replacement = '${1}' . "'" . $escaped_value . "';";
            
            // Perform replacement
            $new_content = preg_replace($pattern, $replacement, $content);
            
            // Validate that replacement worked correctly
            if ($new_content === null) {
                throw new Exception("Regex error while updating {$var_name}");
            }
            
            // Verify the replacement actually changed something and is valid
            if ($new_content === $content) {
                // No change - maybe the pattern didn't match, try alternative
                $alt_pattern = "/(\\\${$var_name}\s*=\s*['\"])[^'\"]*(['\"];)/";
                $alt_replacement = '${1}' . $escaped_value . '${2}';
                $new_content = preg_replace($alt_pattern, $alt_replacement, $content);
                
                if ($new_content === $content || $new_content === null) {
                    error_log("Warning: Could not find pattern to replace for {$var_name}");
                    continue; // Skip this variable
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
            // Syntax error detected - restore original
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
        
        // Verify updates
        $this->verify();
        
        return true;
    }
    
    /**
     * Verify that all values were updated correctly
     */
    private function verify() {
        $content = file_get_contents($this->config_file);
        
        foreach ($this->values as $var_name => $value) {
            // Check if the value appears in the file
            $pattern = "/\\\${$var_name}\s*=\s*['\"]" . preg_quote($value, '/') . "['\"];/";
            if (!preg_match($pattern, $content)) {
                error_log("Warning: Config update verification failed for {$var_name}");
            }
        }
    }
}
