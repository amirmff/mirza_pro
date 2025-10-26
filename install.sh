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
PHP_VERSION="8.2"

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
STEP_TOTAL=14
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
    draw_progress_bar $percent
}

draw_progress_bar() {
    local percent=$1
    local filled=$((percent / 2))
    local empty=$((50 - filled))
    local bar=""
    
    # Build progress bar
    for ((i=0; i<filled; i++)); do bar+="█"; done
    for ((i=0; i<empty; i++)); do bar+="░"; done
    
    echo -e "${BLUE}[${bar}] ${percent}%${NC}"
    echo ""
}

run_with_spinner() {
    local message="$1"
    local command="$2"
    local log_marker="===== $message ====="
    
    echo -e "${YELLOW}▶ $message${NC}"
    echo "$log_marker" >> "$LOG_FILE"
    
    # Run command with timeout
    if timeout 600 bash -c "$command" >> "$LOG_FILE" 2>&1; then
        echo -e "${GREEN}✓ $message - Done${NC}"
        update_progress
        return 0
    else
        echo -e "${RED}✗ $message - Failed or timed out${NC}"
        return 1
    fi
}

run_with_live_output() {
    local message="$1"
    local command="$2"
    local log_marker="===== $message ====="
    
    echo -e "${YELLOW}▶ $message${NC}"
    echo "$log_marker" >> "$LOG_FILE"
    
    # Create temp file for output
    local temp_log=$(mktemp)
    
    # Run command in background and show live progress
    bash -c "$command" > "$temp_log" 2>&1 &
    local pid=$!
    
    # Better spinner with Braille patterns
    local spin='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
    local i=0
    local last_shown=""
    
    while kill -0 $pid 2>/dev/null; do
        i=$(( (i+1) % 10 ))
        
        # Show last meaningful line of output
        if [ -s "$temp_log" ]; then
            # Look for package installation lines
            local current_line=$(tail -n 20 "$temp_log" | \
                grep -E '(Setting up|Unpacking|Preparing|Processing|Selecting|Get:|Fetched)' | \
                tail -n 1 | \
                sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | \
                cut -c1-65)
            
            if [ ! -z "$current_line" ] && [ "$current_line" != "$last_shown" ]; then
                last_shown="$current_line"
                printf "\r\033[K  ${BLUE}${spin:$i:1} ${current_line}...${NC}"
            else
                printf "\r  ${BLUE}${spin:$i:1} Working...${NC}"
            fi
        else
            printf "\r  ${BLUE}${spin:$i:1} Starting...${NC}"
        fi
        
        sleep 0.15
    done
    
    wait $pid
    local exit_code=$?
    
    # Clear the spinner line
    printf "\r\033[K"
    
    # Append to main log
    cat "$temp_log" >> "$LOG_FILE"
    rm -f "$temp_log"
    
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ $message - Done${NC}"
        update_progress
        return 0
    else
        echo -e "${RED}✗ $message - Failed${NC}"
        echo -e "${YELLOW}Check log for details: tail -n 50 $LOG_FILE${NC}"
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
    run_with_live_output "Updating package lists" \
        "apt-get update"
    
    # Fix any broken packages
    run_with_spinner "Fixing any broken packages" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -f -y -qq"
    
    # Install required packages
    run_with_live_output "Installing system dependencies" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y \
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
    
    # Don't start Nginx yet - configure it first with custom port
    run_with_spinner "Stopping default Nginx" \
        "systemctl stop nginx || true"
}

install_php() {
    # Kill any hanging apt processes first
    print_info "Checking for package manager locks"
    killall -q apt apt-get dpkg 2>/dev/null || true
    rm -f /var/lib/apt/lists/lock /var/cache/apt/archives/lock /var/lib/dpkg/lock* 2>/dev/null || true
    dpkg --configure -a 2>/dev/null || true
    sleep 2
    print_success "Package manager ready"
    
    # Add PHP repository
    run_with_live_output "Adding PHP repository (this may take a minute)" \
        "LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php && apt-get update"
    
    # Install PHP and extensions with live output
    echo ""
    print_info "Installing PHP ${PHP_VERSION} and extensions"
    print_info "This will download ~50MB and may take 3-5 minutes"
    echo ""
    
    run_with_live_output "Installing PHP packages" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y -o Dpkg::Options::='--force-confdef' -o Dpkg::Options::='--force-confold' \
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
    # Check if MySQL password already exists from previous install
    if [ -f /root/.mysql_root_password ]; then
        print_info "MySQL already configured from previous install"
        MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password)
        print_success "Using existing MySQL credentials"
        update_progress
        return 0
    fi
    
    # Generate random MySQL root password
    print_info "Generating secure MySQL password"
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
    print_success "Password generated"
    
    # Install MySQL
    run_with_live_output "Installing MySQL server" \
        "DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server"
    
    # Start MySQL if not running
    systemctl start mysql || true
    sleep 3
    
    # Secure MySQL installation
    print_info "Securing MySQL installation"
    
    # Check if we can connect with sudo (fresh install)
    if sudo mysql -e "SELECT 1;" > /dev/null 2>&1; then
        print_info "Configuring fresh MySQL installation"
        sudo mysql <<EOF 2>/dev/null || true
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';
FLUSH PRIVILEGES;
EOF
        print_success "MySQL secured with new root password"
    else
        print_info "MySQL already configured (VPN panel or other service detected)"
        print_info "Will use Debian maintenance credentials for database setup"
        # Save marker that we're using existing MySQL
        echo "EXISTING_MYSQL=true" > /root/.mysql_root_password
    fi
    
    systemctl enable mysql > /dev/null 2>&1
    update_progress
    
    chmod 600 /root/.mysql_root_password 2>/dev/null || true
    print_success "MySQL setup complete"
}

install_composer() {
    run_with_spinner "Installing Composer dependency manager" \
        "curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer"
}

setup_database() {
    print_info "Setting up application database"
    
    # Check if database already exists
    if [ -f /root/.mirza_db_credentials ]; then
        print_info "Database already configured from previous install"
        source /root/.mirza_db_credentials
        print_success "Using existing database credentials"
        update_progress
        return 0
    fi
    
    # Generate database credentials
    DB_NAME="mirza_pro"
    DB_USER="mirza_user"
    DB_PASSWORD=$(openssl rand -base64 24)
    MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password 2>/dev/null || echo "")
    
    print_info "Creating database and user"
    
    local DB_CREATED=false
    
    # Try root password if we have one
    if [ ! -z "$MYSQL_ROOT_PASSWORD" ] && [ "$MYSQL_ROOT_PASSWORD" != "EXISTING_MYSQL=true" ]; then
        if mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" > /dev/null 2>&1; then
            mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOF 2>&1 | grep -v "Warning" || true
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '${DB_USER}'@'localhost';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
            if [ $? -eq 0 ]; then
                print_success "Database created using MySQL root password"
                DB_CREATED=true
            fi
        fi
    fi
    
    # Try Debian maintenance user (works with existing MySQL from VPN panels)
    if [ "$DB_CREATED" = "false" ] && [ -f /etc/mysql/debian.cnf ]; then
        print_info "Using Debian maintenance credentials (existing MySQL detected)"
        if mysql --defaults-file=/etc/mysql/debian.cnf -e "SELECT 1;" > /dev/null 2>&1; then
            mysql --defaults-file=/etc/mysql/debian.cnf <<EOF 2>&1 | grep -v "Warning" || true
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '${DB_USER}'@'localhost';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
            if [ $? -eq 0 ]; then
                print_success "Database created using Debian maintenance user"
                print_info "✓ Your VPN panel database is untouched - Mirza Pro uses separate database"
                DB_CREATED=true
            fi
        fi
    fi
    
    # Fallback to sudo mysql (fresh install)
    if [ "$DB_CREATED" = "false" ]; then
        print_info "Trying sudo mysql access"
        if sudo mysql -e "SELECT 1;" > /dev/null 2>&1; then
            sudo mysql <<EOF 2>&1 | grep -v "Warning" || true
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '${DB_USER}'@'localhost';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
            if [ $? -eq 0 ]; then
                print_success "Database created using sudo"
                DB_CREATED=true
            fi
        fi
    fi
    
    # Check if database creation succeeded
    if [ "$DB_CREATED" = "false" ]; then
        print_error "Failed to create database. Please check MySQL access."
        return 1
    fi
    
    update_progress
    
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
    
    # Get server IP (prefer local detection - faster and more reliable)
    print_info "Detecting server IP"
    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' | grep -oE '^[0-9.]+$' || \
                ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '127.0.0.1' | head -1 || \
                curl -4 -s --max-time 3 icanhazip.com 2>/dev/null | grep -oE '^[0-9.]+$' || \
                echo "YOUR_SERVER_IP")
    print_success "Server IP: $SERVER_IP"
    
    # Remove default config that uses port 80
    rm -f /etc/nginx/sites-enabled/default
    
    cat > /etc/nginx/sites-available/mirza_pro <<'NGINX_EOF'
server {
    listen HTTP_PORT_PLACEHOLDER;
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
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
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
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX_EOF
    
    # Replace port placeholder
    sed -i "s/HTTP_PORT_PLACEHOLDER/${HTTP_PORT}/" /etc/nginx/sites-available/mirza_pro
    
    # Enable site
    ln -sf /etc/nginx/sites-available/mirza_pro /etc/nginx/sites-enabled/
    
    # Test, enable and start Nginx with custom port
    run_with_spinner "Testing and starting Nginx on port $HTTP_PORT" \
        "nginx -t && systemctl enable nginx && systemctl start nginx"
    
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
    # Restore stdin if piped (for curl | bash execution)
    if [ ! -t 0 ]; then
        exec < /dev/tty
    fi
    
    echo ""
    print_info "UFW Firewall Configuration"
    echo ""
    echo -e "${YELLOW}⚠ WARNING: If you have a VPN panel (Marzban, X-UI, etc.) installed,"
    echo "  configuring UFW may interfere with your existing firewall rules."
    echo -e "${NC}"
    echo "Do you want to configure UFW firewall for Mirza Pro?"
    echo "  - Select 'y' if this is a fresh server"
    echo "  - Select 'n' if you have VPN panel or custom firewall rules"
    echo ""
    
    read -p "Configure UFW firewall? [y/N]: " -r
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Skipping UFW configuration"
        print_info "Remember to manually allow ports: SSH($SSH_PORT), HTTP($HTTP_PORT)"
        update_progress
        return 0
    fi
    
    print_info "Configuring UFW firewall"
    
    # Check if UFW is active with existing rules
    if ufw status | grep -q "Status: active"; then
        print_info "UFW is already active. Adding Mirza Pro ports to existing rules..."
        
        # Just add our ports without resetting
        ufw allow ${SSH_PORT}/tcp > /dev/null 2>&1
        print_info "Allowed SSH on port $SSH_PORT"
        
        ufw allow ${HTTP_PORT}/tcp > /dev/null 2>&1
        print_info "Allowed HTTP on port $HTTP_PORT"
        
        if [ ! -z "$HTTPS_PORT" ]; then
            ufw allow ${HTTPS_PORT}/tcp > /dev/null 2>&1
            print_info "Allowed HTTPS on port $HTTPS_PORT"
        fi
        
        print_success "Firewall rules updated (existing rules preserved)"
    else
        # Fresh UFW setup
        print_info "Setting up fresh UFW configuration"
        
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
    fi
    
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

configure_config_file() {
    print_info "Preparing web panel for first-time setup"
    
    # Save database credentials for setup wizard to pre-fill
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials
        
        # Create a JSON file with DB credentials for setup wizard
        cat > "$INSTALL_DIR/webpanel/.db_credentials.json" <<EOF
{
    "db_host": "localhost",
    "db_name": "${DB_NAME}",
    "db_user": "${DB_USER}",
    "db_password": "${DB_PASSWORD}"
}
EOF
        chown www-data:www-data "$INSTALL_DIR/webpanel/.db_credentials.json"
        chmod 600 "$INSTALL_DIR/webpanel/.db_credentials.json"
    fi
    
    # Ensure config.php has correct permissions
    chown www-data:www-data "$INSTALL_DIR/config.php"
    chmod 640 "$INSTALL_DIR/config.php"
    
    print_success "Config file ready for setup wizard"
    update_progress
}

install_cli_tool() {
    print_info "Installing CLI management tool"
    
    # Copy CLI tool to /usr/local/bin
    cp "$INSTALL_DIR/mirza-cli.sh" /usr/local/bin/mirza
    chmod +x /usr/local/bin/mirza
    
    print_success "CLI tool installed: Run 'mirza' to manage bot"
    update_progress
}

print_completion() {
    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' | grep -oE '^[0-9.]+$' || \
                ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '127.0.0.1' | head -1 || \
                curl -4 -s --max-time 3 icanhazip.com 2>/dev/null | grep -oE '^[0-9.]+$' || \
                echo "YOUR_SERVER_IP")
    
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
    echo -e "${BLUE}Management:${NC}"
    echo -e "   ${GREEN}mirza${NC}                               - Open CLI management menu"
    echo ""
    echo -e "${BLUE}Or use these commands directly:${NC}"
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
    configure_config_file
    configure_nginx
    setup_supervisor
    setup_firewall
    create_setup_flag
    install_cli_tool
    
    # Completion
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    print_completion
}

# Run installation
main "$@"
