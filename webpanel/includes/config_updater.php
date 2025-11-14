<?php
/**
 * Config File Updater
 * Properly updates config.php with setup wizard values
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
            // Try to fix permissions automatically using sudo/exec
            $real_path = realpath($this->config_file);
            
            // Try chmod first
            @chmod($real_path, 0664);
            
            // If still not writable, try to change ownership (requires root/sudo)
            if (!is_writable($real_path)) {
                // Get current web server user
                $web_user = 'www-data';
                if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                    $process_user = posix_getpwuid(posix_geteuid());
                    $web_user = $process_user['name'] ?? 'www-data';
                }
                
                // Try to change ownership via exec (if we have permissions)
                @exec("chown {$web_user}:{$web_user} " . escapeshellarg($real_path) . " 2>&1", $chown_out, $chown_code);
                @chmod($real_path, 0664);
                
                // Check again
                if (!is_writable($real_path)) {
                    throw new Exception("Config file not writable: " . $this->config_file . ". Please run as root: chown www-data:www-data " . $real_path . " && chmod 664 " . $real_path);
                }
            }
        }
        
        $content = file_get_contents($this->config_file);
        
        // Update each value
        foreach ($this->values as $var_name => $value) {
            $escaped_value = addslashes($value);
            
            // Pattern to match: $VARNAME = 'anything' or $VARNAME = "{placeholder}";
            // Use a more specific pattern that captures the full assignment
            $pattern = "/(\\\${$var_name}\s*=\s*['\"])([^'\"]*)(['\"];)/";
            
            // Replace with proper escaping - use single quotes to avoid variable interpolation issues
            $replacement = '$1' . $escaped_value . '$3';
            
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Write back
        $result = file_put_contents($this->config_file, $content);
        
        if ($result === false) {
            throw new Exception("Failed to write config file");
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
            // Check if the value appears in the file (in main config section, not early return)
            $pattern = "/\\\${$var_name}\s*=\s*['\"]" . preg_quote($value, '/') . "['\"];/";
            if (!preg_match($pattern, $content)) {
                error_log("Warning: Config update verification failed for {$var_name}");
            }
        }
    }
}

