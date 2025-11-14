<?php
/**
 * Config File Updater - FIXED VERSION
 * Properly handles placeholders and updates ALL instances
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
            // $VARNAME = 'anything'; (including placeholders like {API_KEY})
            // $VARNAME = "anything";
            // This pattern matches the variable declaration and any value (including braces)
            $pattern = "/(\\\${$var_name}\s*=\s*)(['\"])([^'\"]*)(\\2;)/";
            
            // Replace with new value
            $replacement = '${1}\'' . $escaped . '\';';
            
            // Replace ALL instances
            $new_content = preg_replace($pattern, $replacement, $content);
            
            if ($new_content === null) {
                throw new Exception("Regex error updating {$var_name}");
            }
            
            // If no change, try more aggressive pattern
            if ($new_content === $content) {
                // Try pattern that matches placeholders with braces
                $alt_pattern = "/(\\\${$var_name}\s*=\s*)(['\"]?)([^'\";]*)(['\"]?;)/";
                $new_content = preg_replace($alt_pattern, '${1}\'' . $escaped . '\';', $content);
                
                if ($new_content === null || $new_content === $content) {
                    error_log("Warning: Could not update {$var_name} - pattern may not match");
                    continue;
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
            throw new Exception("Syntax error: " . implode("\n", $output));
        }
        
        // Backup original
        @copy($this->config_file, $this->config_file . '.backup.' . date('YmdHis'));
        
        // Replace file
        if (!rename($temp_file, $this->config_file)) {
            @unlink($temp_file);
            throw new Exception("Failed to replace config file");
        }
        
        return true;
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
