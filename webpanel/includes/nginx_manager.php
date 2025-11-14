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
        if (file_put_contents($this->config_file, $content) === false) {
            throw new Exception("Failed to write Nginx config file");
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
        
        if (file_put_contents($this->config_file, $content) === false) {
            throw new Exception("Failed to create Nginx config file");
        }
        
        // Enable site
        $enabled_link = '/etc/nginx/sites-enabled/mirza_pro';
        if (!file_exists($enabled_link)) {
            symlink($this->config_file, $enabled_link);
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
            mkdir($dir, 0755, true);
        }
        
        if (file_exists($this->config_file) && !is_writable($this->config_file)) {
            @chmod($this->config_file, 0644);
            @exec("chown root:root " . escapeshellarg($this->config_file) . " 2>&1");
            if (!is_writable($this->config_file)) {
                throw new Exception("Nginx config file is not writable: {$this->config_file}");
            }
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

