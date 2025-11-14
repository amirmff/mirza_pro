<?php
/**
 * Config File Updater - BULLETPROOF VERSION
 * Updates config.php by replacing lines directly - handles placeholders and all instances
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
        
        // Read file as lines
        $lines = file($this->config_file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new Exception("Failed to read config file");
        }
        
        // Update each variable - replace lines that match the pattern
        foreach ($this->values as $var_name => $value) {
            $escaped = addslashes($value);
            $new_line = "\${$var_name} = '{$escaped}';";
            
            // Find and replace ALL lines matching this variable
            for ($i = 0; $i < count($lines); $i++) {
                // Match: $VARNAME = 'anything'; or $VARNAME = "anything"; or $VARNAME = '{placeholder}';
                if (preg_match("/^\s*\\\${$var_name}\s*=\s*['\"][^'\"]*['\"]\s*;/", $lines[$i])) {
                    $lines[$i] = $new_line;
                }
            }
        }
        
        // Write back to temp file
        $temp_file = $this->config_file . '.tmp.' . time();
        $content = implode("\n", $lines) . "\n";
        file_put_contents($temp_file, $content);
        
        // Validate syntax
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
