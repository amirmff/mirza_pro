<?php
/**
 * Config File Updater - BULLETPROOF VERSION
 * Handles placeholders, updates ALL instances, validates properly
 */

class ConfigUpdater {
    private $config_file;
    private $values;
    
    public function __construct($config_file) {
        $this->config_file = $config_file;
        $this->values = [];
    }
    
    public function set($key, $value) {
        $this->values[$key] = $value;
    }
    
    public function update() {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config file not found: " . $this->config_file);
        }
        
        // Ensure file is writable
        $real_path = realpath($this->config_file);
        if (!is_writable($real_path)) {
            @chmod($real_path, 0664);
            if (!is_writable($real_path)) {
                @exec("chown www-data:www-data " . escapeshellarg($real_path) . " 2>&1");
                @chmod($real_path, 0664);
                if (!is_writable($real_path)) {
                    throw new Exception("Config file not writable. Run: chown www-data:www-data " . $real_path . " && chmod 664 " . $real_path);
                }
            }
        }
        
        // Read original
        $content = file_get_contents($this->config_file);
        $original = $content;
        
        // Update each variable - replace ALL occurrences including placeholders
        foreach ($this->values as $var_name => $value) {
            $escaped = addslashes($value);
            
            // Pattern that matches:
            // $VARNAME = 'anything'; (including placeholders like {API_KEY}, {admin_number})
            // $VARNAME = "anything";
            // This pattern uses a non-greedy match to capture everything between quotes
            $pattern = "/(\\\${$var_name}\s*=\s*)(['\"])(.*?)(\\2;)/s";
            
            // Replace with new value
            $replacement = '${1}\'' . $escaped . '\';';
            
            // Replace ALL instances
            $new_content = preg_replace($pattern, $replacement, $content);
            
            if ($new_content === null) {
                throw new Exception("Regex error updating {$var_name}");
            }
            
            // Verify replacement happened
            if ($new_content === $content) {
                // Try alternative: match without quotes (for edge cases)
                $alt_pattern = "/(\\\${$var_name}\s*=\s*)(['\"]?)([^;]*?)(['\"]?;)/";
                $alt_replacement = '${1}\'' . $escaped . '\';';
                $new_content = preg_replace($alt_pattern, $alt_replacement, $content);
                
                if ($new_content === null || $new_content === $content) {
                    error_log("Warning: Could not update {$var_name} - pattern may not match. Line: " . $this->findVariableLine($content, $var_name));
                    // Try one more time with a very simple pattern
                    $simple_pattern = "/\\\${$var_name}\s*=\s*[^;]+;/";
                    $simple_replacement = "\${$var_name} = '{$escaped}';";
                    $new_content = preg_replace($simple_pattern, $simple_replacement, $content);
                    
                    if ($new_content === null || $new_content === $content) {
                        throw new Exception("Failed to update {$var_name}. Pattern did not match any instances.");
                    }
                }
            }
            
            $content = $new_content;
        }
        
        // Validate syntax
        $temp_file = $this->config_file . '.tmp.' . time();
        file_put_contents($temp_file, $content);
        
        $output = [];
        $code = 0;
        @exec("php -l " . escapeshellarg($temp_file) . " 2>&1", $output, $code);
        
        if ($code !== 0) {
            @unlink($temp_file);
            $error_msg = "Syntax error: " . implode("\n", $output);
            error_log($error_msg);
            throw new Exception($error_msg);
        }
        
        // Backup original
        @copy($this->config_file, $this->config_file . '.backup.' . date('YmdHis'));
        
        // Replace file
        if (!rename($temp_file, $this->config_file)) {
            @unlink($temp_file);
            throw new Exception("Failed to replace config file");
        }
        
        // Verify updates were applied
        $this->verifyUpdates();
        
        return true;
    }
    
    private function findVariableLine($content, $var_name) {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (preg_match("/\\\${$var_name}\s*=/", $line)) {
                return ($i + 1) . ": " . trim($line);
            }
        }
        return "not found";
    }
    
    private function verifyUpdates() {
        $content = file_get_contents($this->config_file);
        foreach ($this->values as $var_name => $value) {
            // Check if value appears in file (in main section after $pdo)
            $main_section_start = strpos($content, '$pdo = new PDO');
            if ($main_section_start !== false) {
                $main_section = substr($content, $main_section_start);
                $pattern = "/\\\${$var_name}\s*=\s*['\"]" . preg_quote($value, '/') . "['\"];/";
                if (!preg_match($pattern, $main_section)) {
                    error_log("Warning: Verification failed for {$var_name} in main section");
                }
            }
        }
    }
    
    public function restartBot() {
        // Stop bot
        @exec('supervisorctl stop mirza_bot 2>&1');
        sleep(1);
        
        // Update supervisor
        @exec('supervisorctl reread 2>&1');
        @exec('supervisorctl update 2>&1');
        
        // Start bot
        @exec('supervisorctl start mirza_bot 2>&1', $out, $code);
        sleep(2);
        
        // Verify
        @exec('supervisorctl status mirza_bot 2>&1', $status, $scode);
        $running = !empty($status[0]) && strpos($status[0], 'RUNNING') !== false;
        
        return $running;
    }
}
