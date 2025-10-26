#!/bin/bash

#########################################
# Mirza Pro - Automated Installation
# Complete deployment on Ubuntu Server
#########################################

# Exit on error, but handle it gracefully
set -euo pipefail
trap 'error_handler $? $LINENO' ERR

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

# Default ports
HTTP_PORT=80
HTTPS_PORT=443
SSH_PORT=22

# Create log file
mkdir -p "$(dirname "$LOG_FILE")"
touch "$LOG_FILE"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

# Global progress tracking
STEP_TOTAL=12
STEP_CURRENT=0

# Functions
error_handler() {
    local exit_code=$1
    local line_number=$2
    print_error "Installation failed at line $line_number with exit code $exit_code"
    print_error "Check the log file: $LOG_FILE"
    print_info "Last 20 lines of log:"
    tail -n 20 "$LOG_FILE" | while read line; do echo "  $line"; done
    exit $exit_code
}

update_progress() {
    STEP_CURRENT=$((STEP_CURRENT + 1))
    local percent=$((STEP_CURRENT * 100 / STEP_TOTAL))
    echo -ne "\r${BLUE}[Progress: ${percent}%]${NC}\n"
}

run_with_spinner() {
    local message="$1"
    local command="$2"
    local log_marker="===== $message ====="
    
    print_info "$message"
    echo "$log_marker" >> "$LOG_FILE"
    
    # Run command with timeout
    if timeout 600 bash -c "$command" >> "$LOG_FILE" 2>&1; then
        print_success "$message - Done"
        update_progress
        return 0
    else
        print_error "$message - Failed or timed out"
        return 1
    fi
}

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

check_port() {
    local port=$1
    if netstat -tuln 2>/dev/null | grep -q ":$port " || ss -tuln 2>/dev/null | grep -q ":$port "; then
        return 0  # Port is in use
    else
        return 1  # Port is free
    fi
}

get_port_process() {
    local port=$1
    lsof -i :$port 2>/dev/null | tail -n 1 | awk '{print $1}' || echo "Unknown"
}

configure_ports() {
    # Restore stdin if piped (for curl | bash execution)
    if [ ! -t 0 ]; then
        exec < /dev/tty
    fi
    
    echo ""
    echo -e "${BLUE}==========================================" 
    echo "   Port Configuration"
    echo -e "==========================================${NC}"
    echo ""
    
    print_info "Checking default ports availability..."
    echo ""
    
    # Check HTTP port
    if check_port 80; then
        local process=$(get_port_process 80)
        print_error "Port 80 is already in use by: $process"
        echo -e "${YELLOW}You can:"
        echo "  1. Stop the service using port 80"
        echo "  2. Choose a different port for Mirza Pro"
        echo -e "${NC}"
        
        read -p "Do you want to use a different HTTP port? [y/N]: " -r
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            while true; do
                read -p "Enter HTTP port (e.g., 8080): " HTTP_PORT
                if [[ $HTTP_PORT =~ ^[0-9]+$ ]] && [ $HTTP_PORT -ge 1024 ] && [ $HTTP_PORT -le 65535 ]; then
                    if check_port $HTTP_PORT; then
                        print_error "Port $HTTP_PORT is also in use. Try another."
                    else
                        print_success "Port $HTTP_PORT is available"
                        break
                    fi
                else
                    print_error "Invalid port. Use 1024-65535."
                fi
            done
        else
            print_error "Cannot proceed without a free HTTP port."
            echo "Please stop the service on port 80 and run installer again."
            exit 1
        fi
    else
        print_success "Port 80 is available for HTTP"
    fi
    
    # Check HTTPS port
    if check_port 443; then
        local process=$(get_port_process 443)
        print_error "Port 443 is already in use by: $process"
        echo -e "${YELLOW}Note: HTTPS will be configured later via the web panel${NC}"
        
        read -p "Enter HTTPS port (or press Enter to skip SSL for now): " HTTPS_PORT_INPUT
        if [ ! -z "$HTTPS_PORT_INPUT" ]; then
            HTTPS_PORT=$HTTPS_PORT_INPUT
        else
            HTTPS_PORT=""
        fi
    else
        print_success "Port 443 is available for HTTPS"
    fi
    
    # Check SSH port (informational only)
    SSH_PORT=$(ss -tlnp 2>/dev/null | grep sshd | grep -oP ':\K[0-9]+' | head -1 || echo "22")
    print_info "SSH is running on port: $SSH_PORT"
    
    echo ""
    echo -e "${GREEN}Port Configuration Summary:${NC}"
    echo "  HTTP:  $HTTP_PORT"
    echo "  HTTPS: ${HTTPS_PORT:-Not configured yet}"
    echo "  SSH:   $SSH_PORT"
    echo ""
    
    read -p "Press Enter to continue with these settings..." -r
    echo ""
}

install_dependencies() {
    # Update package list
    run_with_spinner "Updating package lists" \
        "apt-get update -qq"
    
    # Fix any broken packages
    run_with_spinner "Fixing any broken packages" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -f -y -qq"
    
    # Install required packages
    run_with_spinner "Installing system dependencies" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
            software-properties-common \
            curl \
            wget \
            git \
            unzip \
            supervisor \
            certbot \
            python3-certbot-nginx \
            ufw \
            htop"
}

install_nginx() {
    run_with_spinner "Installing Nginx web server" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx"
    
    run_with_spinner "Starting Nginx service" \
        "systemctl enable nginx && systemctl start nginx"
}

install_php() {
    # Add PHP repository
    run_with_spinner "Adding PHP repository" \
        "LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php && apt-get update -qq"
    
    # Install PHP and extensions
    run_with_spinner "Installing PHP ${PHP_VERSION} and extensions" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
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
            php${PHP_VERSION}-soap"
    
    # Configure PHP
    print_info "Configuring PHP settings"
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 50M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/post_max_size = .*/post_max_size = 50M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i "s/max_execution_time = .*/max_execution_time = 300/" /etc/php/${PHP_VERSION}/fpm/php.ini
    print_success "PHP configured"
    
    run_with_spinner "Starting PHP-FPM service" \
        "systemctl enable php${PHP_VERSION}-fpm && systemctl restart php${PHP_VERSION}-fpm"
}

install_mysql() {
    # Generate random MySQL root password
    print_info "Generating secure MySQL password"
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
    print_success "Password generated"
    
    # Install MySQL
    run_with_spinner "Installing MySQL server" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server"
    
    # Start MySQL if not running
    systemctl start mysql || true
    sleep 3
    
    # Secure MySQL installation
    print_info "Securing MySQL installation"
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';" 2>&1 | grep -v "Warning" || true
    mysql -e "DELETE FROM mysql.user WHERE User='';" 2>&1 | grep -v "Warning" || true
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" 2>&1 | grep -v "Warning" || true
    mysql -e "DROP DATABASE IF EXISTS test;" 2>&1 | grep -v "Warning" || true
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" 2>&1 | grep -v "Warning" || true
    mysql -e "FLUSH PRIVILEGES;" 2>&1 | grep -v "Warning" || true
    print_success "MySQL secured"
    
    systemctl enable mysql > /dev/null 2>&1
    update_progress
    
    # Save credentials
    echo "$MYSQL_ROOT_PASSWORD" > /root/.mysql_root_password
    chmod 600 /root/.mysql_root_password
    print_success "MySQL credentials saved to /root/.mysql_root_password"
}

install_composer() {
    run_with_spinner "Installing Composer dependency manager" \
        "curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer"
}

setup_database() {
    print_info "Setting up application database"
    
    # Generate database credentials
    DB_NAME="mirza_pro"
    DB_USER="mirza_user"
    DB_PASSWORD=$(openssl rand -base64 24)
    MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password)
    
    # Create database and user
    run_with_spinner "Creating database and user" \
        "mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e \"CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}'; GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;\""
    
    # Save credentials
    cat > /root/.mirza_db_credentials <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
EOF
    chmod 600 /root/.mirza_db_credentials
    print_success "Database credentials saved"
}

copy_files() {
    print_info "Cloning Mirza Pro from GitHub"
    
    # Remove old directory if exists
    rm -rf "$INSTALL_DIR"
    
    # Clone from GitHub
    run_with_spinner "Downloading latest version" \
        "git clone -q https://github.com/amirmff/mirza_pro.git $INSTALL_DIR"
    
    # Create necessary directories
    mkdir -p "$INSTALL_DIR/logs"
    mkdir -p "$INSTALL_DIR/backups"
    mkdir -p "$INSTALL_DIR/webpanel/assets"
    
    # Set permissions
    print_info "Setting file permissions"
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR/logs"
    chmod -R 775 "$INSTALL_DIR/backups"
    chmod -R 775 "$INSTALL_DIR/webpanel/assets"
    print_success "Files installed"
    update_progress
}

configure_nginx() {
    print_info "Configuring Nginx web server"
    
    # Get server IP
    print_info "Detecting server IP"
    SERVER_IP=$(curl -s --max-time 10 ifconfig.me || echo "YOUR_SERVER_IP")
    print_success "Server IP: $SERVER_IP"
    
    cat > /etc/nginx/sites-available/mirza_pro <<NGINX_EOF
server {
    listen ${HTTP_PORT};
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
    run_with_spinner "Testing and reloading Nginx" \
        "nginx -t && systemctl reload nginx"
    
    print_success "Nginx configured: http://$SERVER_IP"
}

setup_supervisor() {
    print_info "Setting up Supervisor for bot process management"
    
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
    
    run_with_spinner "Configuring and starting Supervisor" \
        "supervisorctl reread && supervisorctl update"
}

setup_firewall() {
    print_info "Configuring UFW firewall"
    
    # Reset UFW
    ufw --force reset > /dev/null 2>&1
    
    # Default policies
    ufw default deny incoming > /dev/null 2>&1
    ufw default allow outgoing > /dev/null 2>&1
    
    # Allow SSH
    ufw allow ${SSH_PORT}/tcp > /dev/null 2>&1
    print_info "Allowed SSH on port $SSH_PORT"
    
    # Allow HTTP
    ufw allow ${HTTP_PORT}/tcp > /dev/null 2>&1
    print_info "Allowed HTTP on port $HTTP_PORT"
    
    # Allow HTTPS if configured
    if [ ! -z "$HTTPS_PORT" ]; then
        ufw allow ${HTTPS_PORT}/tcp > /dev/null 2>&1
        print_info "Allowed HTTPS on port $HTTPS_PORT"
    fi
    
    # Enable firewall
    ufw --force enable > /dev/null 2>&1
    
    print_success "Firewall configured"
    update_progress
}

create_setup_flag() {
    # Create flag file to trigger setup wizard
    print_info "Creating setup wizard trigger"
    touch "$INSTALL_DIR/webpanel/.needs_setup"
    chown www-data:www-data "$INSTALL_DIR/webpanel/.needs_setup"
    print_success "Setup flag created"
    update_progress
}

print_completion() {
    SERVER_IP=$(curl -s --max-time 10 ifconfig.me || hostname -I | awk '{print $1}')
    
    echo ""
    echo -e "${GREEN}=========================================="
    echo "  Installation Complete!"
    echo -e "==========================================${NC}"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo ""
    echo "1. Access the web panel:"
    if [ "$HTTP_PORT" = "80" ]; then
        echo "   http://$SERVER_IP/webpanel/"
    else
        echo "   http://$SERVER_IP:$HTTP_PORT/webpanel/"
    fi
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
    echo -e "${BLUE}Port Configuration:${NC}"
    echo "   HTTP:  $HTTP_PORT"
    if [ ! -z "$HTTPS_PORT" ]; then
        echo "   HTTPS: $HTTPS_PORT"
    fi
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
    # Clear screen for clean output
    clear
    
    print_header
    
    # Pre-checks
    print_info "Running pre-installation checks"
    check_root
    check_os
    
    # Port configuration
    configure_ports
    
    echo ""
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    print_info "Starting automated installation"
    print_info "This will take 5-10 minutes"
    print_info "Log file: $LOG_FILE"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    sleep 2
    
    # Installation steps
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
    
    # Completion
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    print_completion
}

# Run installation
main "$@"
