<?php
/**
 * Config File Updater - COMPLETE REWRITE
 * Safely updates config.php with proper validation
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
        
        // Update each variable - replace ALL occurrences
        foreach ($this->values as $var_name => $value) {
            $escaped = addslashes($value);
            
            // Pattern: $VARNAME = 'anything'; or $VARNAME = "anything";
            // Replace ALL instances in the file
            $patterns = [
                "/(\\\${$var_name}\s*=\s*)['\"][^'\"]*['\"];/",
                "/(\\\${$var_name}\s*=\s*\{[^}]+\};)/"
            ];
            
            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, '${1}\'' . $escaped . '\';', $content);
            }
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
