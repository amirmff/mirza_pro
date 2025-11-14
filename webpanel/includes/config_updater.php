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
        if (!file_exists($this->config_file) || !is_writable($this->config_file)) {
            throw new Exception("Config file not writable: " . $this->config_file);
        }
        
        $content = file_get_contents($this->config_file);
        
        // Update each value
        foreach ($this->values as $var_name => $value) {
            $escaped_value = addslashes($value);
            
            // Pattern to match: $VARNAME = 'anything' or $VARNAME = "{placeholder}";
            $pattern = "/(\\\${$var_name}\s*=\s*['\"])[^'\"]*(['\"];)/";
            $replacement = "\$1{$escaped_value}\$2";
            
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

