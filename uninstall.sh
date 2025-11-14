#!/bin/bash
#########################################
# Mirza Pro - Complete Uninstall Script
# Removes all components from the system
#########################################

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

INSTALL_DIR="/var/www/mirza_pro"
NGINX_SITE="/etc/nginx/sites-available/mirza_pro"
NGINX_ENABLED="/etc/nginx/sites-enabled/mirza_pro"
SUPERVISOR_CONFIG="/etc/supervisor/conf.d/mirza_bot.conf"
CLI_TOOL="/usr/local/bin/mirza"

print_header() {
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  Mirza Pro - Complete Uninstall${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_header

# Confirmation
echo -e "${YELLOW}⚠ WARNING: This will completely remove Mirza Pro from your system!${NC}"
echo -e "${YELLOW}This includes:${NC}"
echo "  - Application files"
echo "  - Database (if you choose)"
echo "  - Nginx configuration"
echo "  - Supervisor configuration"
echo "  - Log files"
echo "  - CLI tool"
echo ""
read -p "Are you sure you want to continue? [y/N]: " -r
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_info "Uninstall cancelled"
    exit 0
fi

echo ""
print_info "Starting uninstall process..."
echo ""

# Stop services
print_info "Stopping services..."

if systemctl is-active --quiet supervisor; then
    supervisorctl stop mirza_bot 2>/dev/null || true
    print_success "Bot process stopped"
fi

# Remove supervisor configuration
if [ -f "$SUPERVISOR_CONFIG" ]; then
    print_info "Removing Supervisor configuration..."
    rm -f "$SUPERVISOR_CONFIG"
    supervisorctl reread 2>/dev/null || true
    supervisorctl update 2>/dev/null || true
    print_success "Supervisor configuration removed"
fi

# Remove Nginx configuration
if [ -f "$NGINX_SITE" ]; then
    print_info "Removing Nginx configuration..."
    rm -f "$NGINX_ENABLED" 2>/dev/null || true
    rm -f "$NGINX_SITE"
    nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true
    print_success "Nginx configuration removed"
fi

# Remove application files
if [ -d "$INSTALL_DIR" ]; then
    print_info "Removing application files..."
    rm -rf "$INSTALL_DIR"
    print_success "Application files removed"
fi

# Remove CLI tool
if [ -f "$CLI_TOOL" ]; then
    print_info "Removing CLI tool..."
    rm -f "$CLI_TOOL"
    print_success "CLI tool removed"
fi

# Remove log files
print_info "Removing log files..."
rm -f /var/log/mirza_bot.log 2>/dev/null || true
rm -f /var/log/mirza_pro_install.log 2>/dev/null || true
rm -f /tmp/ssl_install_*.log 2>/dev/null || true
print_success "Log files removed"

# Remove credential files
print_info "Removing credential files..."
rm -f /root/.mirza_db_credentials 2>/dev/null || true
rm -f /root/.mysql_root_password 2>/dev/null || true
print_success "Credential files removed"

# Database removal option
echo ""
read -p "Do you want to remove the Mirza Pro database? [y/N]: " -r
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Removing database..."
    
    # Try to load database credentials
    DB_NAME=""
    DB_USER=""
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials 2>/dev/null || true
    fi
    
    # If we have credentials, try to remove database
    if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
        MYSQL_ROOT_PASS=""
        if [ -f /root/.mysql_root_password ]; then
            MYSQL_ROOT_PASS=$(cat /root/.mysql_root_password 2>/dev/null || echo "")
        fi
        
        if [ -n "$MYSQL_ROOT_PASS" ] && [ "$MYSQL_ROOT_PASS" != "EXISTING_MYSQL=true" ]; then
            mysql -uroot -p"$MYSQL_ROOT_PASS" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/dev/null || true
            mysql -uroot -p"$MYSQL_ROOT_PASS" -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/dev/null || true
            print_success "Database and user removed"
        else
            # Try without password (Debian maintenance)
            mysql -uroot -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/dev/null || true
            mysql -uroot -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/dev/null || true
            print_success "Database removal attempted"
        fi
    else
        print_warning "Could not find database credentials. Database may need manual removal."
    fi
else
    print_info "Database kept intact"
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
print_success "Uninstall completed!"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
print_info "All Mirza Pro components have been removed."
print_info "You can now run a fresh installation."
echo ""

