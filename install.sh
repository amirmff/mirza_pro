#!/bin/bash

#########################################
# Mirza Pro - Automated Installation
# Complete deployment on Ubuntu Server
#########################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/var/www/mirza_pro"
LOG_FILE="/var/log/mirza_pro_install.log"
PHP_VERSION="8.1"

# Functions
print_header() {
    echo -e "${BLUE}"
    echo "=========================================="
    echo "   Mirza Pro - Automated Installation"
    echo "=========================================="
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "Please run as root (use sudo)"
        exit 1
    fi
}

check_os() {
    if [ ! -f /etc/lsb-release ]; then
        print_error "This script is for Ubuntu only"
        exit 1
    fi
    
    . /etc/lsb-release
    if [[ ! "$DISTRIB_ID" == "Ubuntu" ]]; then
        print_error "This script is for Ubuntu only"
        exit 1
    fi
    
    print_success "Ubuntu detected: $DISTRIB_RELEASE"
}

install_dependencies() {
    print_info "Installing system dependencies..."
    
    # Update package list
    apt-get update >> "$LOG_FILE" 2>&1
    
    # Install required packages
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        software-properties-common \
        curl \
        wget \
        git \
        unzip \
        supervisor \
        certbot \
        python3-certbot-nginx \
        ufw \
        htop \
        >> "$LOG_FILE" 2>&1
    
    print_success "System dependencies installed"
}

install_nginx() {
    print_info "Installing Nginx..."
    
    apt-get install -y nginx >> "$LOG_FILE" 2>&1
    systemctl enable nginx >> "$LOG_FILE" 2>&1
    systemctl start nginx >> "$LOG_FILE" 2>&1
    
    print_success "Nginx installed and started"
}

install_php() {
    print_info "Installing PHP $PHP_VERSION..."
    
    # Add PHP repository
    add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1
    apt-get update >> "$LOG_FILE" 2>&1
    
    # Install PHP and extensions
    apt-get install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-soap \
        >> "$LOG_FILE" 2>&1
    
    # Configure PHP
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 50M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/post_max_size = .*/post_max_size = 50M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/max_execution_time = .*/max_execution_time = 300/" /etc/php/${PHP_VERSION}/fpm/php.ini
    
    systemctl enable php${PHP_VERSION}-fpm >> "$LOG_FILE" 2>&1
    systemctl restart php${PHP_VERSION}-fpm >> "$LOG_FILE" 2>&1
    
    print_success "PHP $PHP_VERSION installed and configured"
}

install_mysql() {
    print_info "Installing MySQL..."
    
    # Generate random MySQL root password
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
    
    # Install MySQL
    DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server >> "$LOG_FILE" 2>&1
    
    # Secure MySQL installation
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';" >> "$LOG_FILE" 2>&1
    mysql -e "DELETE FROM mysql.user WHERE User='';" >> "$LOG_FILE" 2>&1
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" >> "$LOG_FILE" 2>&1
    mysql -e "DROP DATABASE IF EXISTS test;" >> "$LOG_FILE" 2>&1
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" >> "$LOG_FILE" 2>&1
    mysql -e "FLUSH PRIVILEGES;" >> "$LOG_FILE" 2>&1
    
    systemctl enable mysql >> "$LOG_FILE" 2>&1
    
    # Save credentials
    echo "$MYSQL_ROOT_PASSWORD" > /root/.mysql_root_password
    chmod 600 /root/.mysql_root_password
    
    print_success "MySQL installed (root password saved to /root/.mysql_root_password)"
}

install_composer() {
    print_info "Installing Composer..."
    
    curl -sS https://getcomposer.org/installer | php >> "$LOG_FILE" 2>&1
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    print_success "Composer installed"
}

setup_database() {
    print_info "Setting up database..."
    
    # Generate database credentials
    DB_NAME="mirza_pro"
    DB_USER="mirza_user"
    DB_PASSWORD=$(openssl rand -base64 24)
    MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password)
    
    # Create database and user
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOF >> "$LOG_FILE" 2>&1
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    # Save credentials
    cat > /root/.mirza_db_credentials <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
EOF
    chmod 600 /root/.mirza_db_credentials
    
    print_success "Database created (credentials saved to /root/.mirza_db_credentials)"
}

copy_files() {
    print_info "Setting up application files..."
    
    # Create directory
    mkdir -p "$INSTALL_DIR"
    
    # Copy all files (assuming script is run from project root)
    cp -r ./* "$INSTALL_DIR/"
    
    # Set permissions
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR/webpanel/assets"
    
    print_success "Files copied to $INSTALL_DIR"
}

configure_nginx() {
    print_info "Configuring Nginx..."
    
    # Get server IP
    SERVER_IP=$(curl -s ifconfig.me)
    
    cat > /etc/nginx/sites-available/mirza_pro <<'NGINX_EOF'
server {
    listen 80;
    server_name _;
    
    root /var/www/mirza_pro;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Protect sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /config.php$ {
        deny all;
    }
    
    # Web panel
    location /webpanel {
        try_files $uri $uri/ /webpanel/index.php?$query_string;
    }
    
    # Telegram webhook
    location /webhooks.php {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX_EOF
    
    # Enable site
    ln -sf /etc/nginx/sites-available/mirza_pro /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    
    # Test and reload
    nginx -t >> "$LOG_FILE" 2>&1
    systemctl reload nginx >> "$LOG_FILE" 2>&1
    
    print_success "Nginx configured (accessible at http://$SERVER_IP)"
}

setup_supervisor() {
    print_info "Setting up Supervisor for bot monitoring..."
    
    cat > /etc/supervisor/conf.d/mirza_pro.conf <<EOF
[program:mirza_pro_bot]
command=/usr/bin/php $INSTALL_DIR/bot_daemon.php
directory=$INSTALL_DIR
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/mirza_pro_bot.log
stopwaitsecs=3600
EOF
    
    supervisorctl reread >> "$LOG_FILE" 2>&1
    supervisorctl update >> "$LOG_FILE" 2>&1
    
    print_success "Supervisor configured"
}

setup_firewall() {
    print_info "Configuring firewall..."
    
    # Reset UFW
    ufw --force reset >> "$LOG_FILE" 2>&1
    
    # Default policies
    ufw default deny incoming >> "$LOG_FILE" 2>&1
    ufw default allow outgoing >> "$LOG_FILE" 2>&1
    
    # Allow SSH, HTTP, HTTPS
    ufw allow 22/tcp >> "$LOG_FILE" 2>&1
    ufw allow 80/tcp >> "$LOG_FILE" 2>&1
    ufw allow 443/tcp >> "$LOG_FILE" 2>&1
    
    # Enable firewall
    ufw --force enable >> "$LOG_FILE" 2>&1
    
    print_success "Firewall configured"
}

create_setup_flag() {
    # Create flag file to trigger setup wizard
    touch "$INSTALL_DIR/webpanel/.needs_setup"
    chown www-data:www-data "$INSTALL_DIR/webpanel/.needs_setup"
}

print_completion() {
    SERVER_IP=$(curl -s ifconfig.me)
    
    echo ""
    echo -e "${GREEN}=========================================="
    echo "  Installation Complete!"
    echo -e "==========================================${NC}"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo ""
    echo "1. Access the web panel:"
    echo "   http://$SERVER_IP/webpanel/"
    echo ""
    echo "2. Complete the setup wizard with:"
    echo "   - Telegram Bot Token"
    echo "   - Admin User ID"
    echo "   - Domain name (optional)"
    echo ""
    echo "3. Database credentials (saved securely):"
    echo "   /root/.mirza_db_credentials"
    echo ""
    echo "4. MySQL root password:"
    echo "   /root/.mysql_root_password"
    echo ""
    echo -e "${YELLOW}For SSL/HTTPS setup:${NC}"
    echo "   Use the web panel after initial setup"
    echo ""
    echo -e "${BLUE}Useful commands:${NC}"
    echo "   supervisorctl status mirza_pro_bot  - Check bot status"
    echo "   supervisorctl restart mirza_pro_bot - Restart bot"
    echo "   tail -f /var/log/mirza_pro_bot.log  - View bot logs"
    echo ""
    echo -e "${GREEN}Installation log: $LOG_FILE${NC}"
    echo ""
}

# Main installation
main() {
    print_header
    
    check_root
    check_os
    
    print_info "Starting installation..."
    echo "This may take 5-10 minutes..."
    echo ""
    
    install_dependencies
    install_nginx
    install_php
    install_mysql
    install_composer
    setup_database
    copy_files
    configure_nginx
    setup_supervisor
    setup_firewall
    create_setup_flag
    
    print_completion
}

# Run installation
main "$@"
