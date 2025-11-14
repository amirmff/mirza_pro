<?php
/**
 * Nginx Configuration Manager
 * Handles Nginx configuration updates for domain and SSL setup
 */

class NginxManager {
    private $config_file = '/etc/nginx/sites-available/mirza_pro';
    private $install_dir;
    
    public function __construct($install_dir = '/var/www/mirza_pro') {
        $this->install_dir = $install_dir;
    }
    
    /**
     * Get valid Nginx configuration template
     */
    private function getConfigTemplate($domain = null) {
        $server_name = $domain ?: '_';
        $root = $this->install_dir;
        
        return <<<NGINX_CONFIG
server {
    listen 80;
    listen [::]:80;
    server_name {$server_name};
    
    root {$root};
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # PHP handling
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Protect sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /config.php\$ {
        deny all;
    }
    
    # Web panel
    location /webpanel {
        try_files \$uri \$uri/ /webpanel/index.php?\$query_string;
    }
    
    # Telegram webhook
    location /webhooks.php {
        try_files \$uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
NGINX_CONFIG;
    }
    
    /**
     * Update server_name in Nginx config
     */
    public function updateDomain($domain) {
        if (empty($domain)) {
            throw new Exception("Domain cannot be empty");
        }
        
        // Ensure config file exists
        if (!file_exists($this->config_file)) {
            $this->createConfig($domain);
        }
        
        // Make sure file is writable
        $this->ensureWritable();
        
        // Read current config
        $content = file_get_contents($this->config_file);
        
        // Update server_name - handle both HTTP and HTTPS blocks
        $patterns = [
            // HTTP block
            "/(server\s*\{[^}]*server_name\s+)[^;]+(;)/s",
            // Or simple replacement
            "/(server_name\s+)[^;]+(;)/"
        ];
        
        $updated = false;
        foreach ($patterns as $pattern) {
            $new_content = preg_replace($pattern, "\${1}{$domain}\${2}", $content);
            if ($new_content !== $content) {
                $content = $new_content;
                $updated = true;
                break;
            }
        }
        
        // If no match found, create new config
        if (!$updated) {
            $content = $this->getConfigTemplate($domain);
        }
        
        // Write updated config
        $write_success = false;
        if (is_writable($this->config_file)) {
            $write_success = file_put_contents($this->config_file, $content) !== false;
        }
        
        // If direct write failed, try via sudo
        if (!$write_success) {
            $temp_file = sys_get_temp_dir() . '/mirza_nginx_' . uniqid() . '.conf';
            file_put_contents($temp_file, $content);
            @exec("sudo cp " . escapeshellarg($temp_file) . " " . escapeshellarg($this->config_file) . " 2>&1", $cp_out, $cp_code);
            @unlink($temp_file);
            
            if ($cp_code !== 0 || !file_exists($this->config_file)) {
                throw new Exception("Failed to write Nginx config file. Please run: sudo chown www-data:www-data {$this->config_file} && sudo chmod 664 {$this->config_file}");
            }
        }
        
        // Test configuration
        $this->testConfig();
        
        return true;
    }
    
    /**
     * Create new Nginx config file
     */
    public function createConfig($domain = null) {
        $this->ensureWritable();
        $content = $this->getConfigTemplate($domain);
        
        $write_success = false;
        if (is_writable($this->config_file) || !file_exists($this->config_file)) {
            $write_success = @file_put_contents($this->config_file, $content) !== false;
        }
        
        // If direct write failed, try via sudo
        if (!$write_success) {
            $temp_file = sys_get_temp_dir() . '/mirza_nginx_' . uniqid() . '.conf';
            file_put_contents($temp_file, $content);
            @exec("sudo cp " . escapeshellarg($temp_file) . " " . escapeshellarg($this->config_file) . " 2>&1", $cp_out, $cp_code);
            @unlink($temp_file);
            
            if ($cp_code !== 0 || !file_exists($this->config_file)) {
                throw new Exception("Failed to create Nginx config file. Please run: sudo chown www-data:www-data " . dirname($this->config_file) . " && sudo chmod 755 " . dirname($this->config_file));
            }
        }
        
        // Enable site
        $enabled_link = '/etc/nginx/sites-enabled/mirza_pro';
        if (!file_exists($enabled_link)) {
            @symlink($this->config_file, $enabled_link);
            if (!file_exists($enabled_link)) {
                @exec("sudo ln -sf " . escapeshellarg($this->config_file) . " " . escapeshellarg($enabled_link) . " 2>&1");
            }
        }
        
        $this->testConfig();
    }
    
    /**
     * Test Nginx configuration
     */
    public function testConfig() {
        exec('nginx -t 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            $error = implode("\n", $output);
            throw new Exception("Nginx configuration test failed: {$error}");
        }
        return true;
    }
    
    /**
     * Reload Nginx
     */
    public function reload() {
        $this->testConfig();
        exec('systemctl reload nginx 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            $error = implode("\n", $output);
            throw new Exception("Failed to reload Nginx: {$error}");
        }
        return true;
    }
    
    /**
     * Ensure config file is writable
     */
    private function ensureWritable() {
        $dir = dirname($this->config_file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        // Try to make file writable if it exists
        if (file_exists($this->config_file) && !is_writable($this->config_file)) {
            // Try chmod first
            @chmod($this->config_file, 0664);
            
            // Try chown to www-data (common web server user)
            @exec("chown www-data:www-data " . escapeshellarg($this->config_file) . " 2>&1", $chown_out, $chown_code);
            
            // If that doesn't work, try root
            if (!is_writable($this->config_file)) {
                @exec("chown root:root " . escapeshellarg($this->config_file) . " 2>&1", $chown_out2, $chown_code2);
                @chmod($this->config_file, 0664);
            }
            
            // If still not writable, try using sudo
            if (!is_writable($this->config_file)) {
                @exec("sudo chown www-data:www-data " . escapeshellarg($this->config_file) . " 2>&1", $sudo_out, $sudo_code);
                @exec("sudo chmod 664 " . escapeshellarg($this->config_file) . " 2>&1", $sudo_out2, $sudo_code2);
            }
            
            // Final check
            if (!is_writable($this->config_file)) {
                // Try to write using exec with sudo
                $can_write_via_sudo = false;
                $test_content = "# Test write\n";
                @exec("echo " . escapeshellarg($test_content) . " | sudo tee " . escapeshellarg($this->config_file) . " > /dev/null 2>&1", $tee_out, $tee_code);
                if ($tee_code === 0) {
                    $can_write_via_sudo = true;
                }
                
                if (!$can_write_via_sudo) {
                    throw new Exception("Nginx config file is not writable: {$this->config_file}. Please run: sudo chown www-data:www-data {$this->config_file} && sudo chmod 664 {$this->config_file}");
                }
            }
        }
        
        // Ensure directory is writable for creating new files
        if (!is_writable($dir)) {
            @chmod($dir, 0755);
            @exec("chown root:root " . escapeshellarg($dir) . " 2>&1");
        }
    }
    
    /**
     * Install SSL certificate using Certbot
     */
    public function installSSL($domain, $email) {
        if (empty($domain)) {
            throw new Exception("Domain is required for SSL installation");
        }
        
        // Ensure domain is set in Nginx config first
        $this->updateDomain($domain);
        $this->reload();
        
        // Ensure certbot is installed
        exec('which certbot 2>&1', $certbot_check, $certbot_exists);
        if ($certbot_exists !== 0) {
            exec('apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-nginx 2>&1', $install_output, $install_code);
            if ($install_code !== 0) {
                throw new Exception("Failed to install Certbot: " . implode("\n", $install_output));
            }
        }
        
        // Issue SSL certificate
        $email = $email ?: "admin@{$domain}";
        $certbot_cmd = "certbot --nginx -d {$domain} --redirect --non-interactive --agree-tos -m {$email} 2>&1";
        exec($certbot_cmd, $ssl_output, $ssl_code);
        
        if ($ssl_code !== 0) {
            $error = implode("\n", array_slice($ssl_output, -5));
            throw new Exception("SSL installation failed: {$error}");
        }
        
        // Reload Nginx after SSL installation
        $this->reload();
        
        return true;
    }
    
    /**
     * Check if SSL is installed for domain
     */
    public function hasSSL($domain) {
        $cert_path = "/etc/letsencrypt/live/{$domain}/cert.pem";
        return file_exists($cert_path);
    }
}

