#!/bin/bash
# Comprehensive Installation Test Script
# Run this on your VPS after installation

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

INSTALL_DIR="/var/www/mirza_pro"
LOG_FILE="/tmp/mirza_test_$(date +%Y%m%d_%H%M%S).log"

echo "Test results will be saved to: $LOG_FILE"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

print_header() {
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  Mirza Pro - Comprehensive Installation Test${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

print_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Test 1: Check if installation directory exists
test_installation_dir() {
    print_test "Checking installation directory..."
    if [ -d "$INSTALL_DIR" ]; then
        print_success "Installation directory exists: $INSTALL_DIR"
        if [ -f "$INSTALL_DIR/index.php" ]; then
            print_success "Main bot file (index.php) exists"
        else
            print_error "Main bot file (index.php) not found"
            return 1
        fi
    else
        print_error "Installation directory not found: $INSTALL_DIR"
        return 1
    fi
}

# Test 2: Check required services
test_services() {
    print_test "Checking required services..."
    
    # Nginx
    if systemctl is-active --quiet nginx; then
        print_success "Nginx is running"
    else
        print_error "Nginx is not running"
        systemctl status nginx --no-pager -l || true
    fi
    
    # PHP-FPM
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    if systemctl is-active --quiet "php${PHP_VERSION}-fpm"; then
        print_success "PHP-FPM (${PHP_VERSION}) is running"
    else
        print_error "PHP-FPM is not running"
        systemctl status "php${PHP_VERSION}-fpm" --no-pager -l || true
    fi
    
    # MySQL
    if systemctl is-active --quiet mysql; then
        print_success "MySQL is running"
    else
        print_error "MySQL is not running"
        systemctl status mysql --no-pager -l || true
    fi
    
    # Supervisor
    if systemctl is-active --quiet supervisor; then
        print_success "Supervisor is running"
    else
        print_error "Supervisor is not running"
        systemctl status supervisor --no-pager -l || true
    fi
}

# Test 3: Check bot process
test_bot_process() {
    print_test "Checking bot process..."
    if supervisorctl status mirza_bot 2>/dev/null | grep -q RUNNING; then
        print_success "Bot process is RUNNING"
        supervisorctl status mirza_bot
    else
        print_warning "Bot process is not running"
        supervisorctl status mirza_bot || true
        print_warning "This is normal if setup wizard hasn't been completed yet"
    fi
}

# Test 4: Check database connection
test_database() {
    print_test "Checking database connection..."
    
    # Try to load credentials
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials
        print_success "Database credentials file found"
        
        if mysql -h localhost -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1; then
            print_success "Database connection successful"
            
            # Check if tables exist
            TABLE_COUNT=$(mysql -h localhost -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l)
            if [ "$TABLE_COUNT" -gt 1 ]; then
                print_success "Database has $((TABLE_COUNT-1)) tables"
            else
                print_warning "Database exists but may not have tables yet (run setup wizard)"
            fi
        else
            print_error "Database connection failed"
            return 1
        fi
    else
        print_warning "Database credentials file not found (normal if not installed yet)"
    fi
}

# Test 5: Check web server configuration
test_nginx_config() {
    print_test "Checking Nginx configuration..."
    if nginx -t 2>&1 | grep -q "successful"; then
        print_success "Nginx configuration is valid"
    else
        print_error "Nginx configuration has errors"
        nginx -t
        return 1
    fi
    
    # Check if site is enabled
    if [ -L /etc/nginx/sites-enabled/mirza_pro ]; then
        print_success "Nginx site is enabled"
    else
        print_warning "Nginx site may not be enabled"
    fi
}

# Test 6: Check web panel accessibility
test_web_panel() {
    print_test "Checking web panel files..."
    
    if [ -f "$INSTALL_DIR/webpanel/index.php" ]; then
        print_success "Web panel index.php exists"
    else
        print_error "Web panel index.php not found"
        return 1
    fi
    
    if [ -f "$INSTALL_DIR/webpanel/setup.php" ]; then
        print_success "Setup wizard exists"
    else
        print_error "Setup wizard not found"
        return 1
    fi
    
    # Check if setup flag exists
    if [ -f "$INSTALL_DIR/webpanel/.needs_setup" ]; then
        print_warning "Setup wizard flag exists - setup needed"
    else
        print_success "Setup wizard flag not found - setup may be complete"
    fi
}

# Test 7: Check config.php
test_config_file() {
    print_test "Checking config.php..."
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        print_success "config.php exists"
        
        # Check if it has placeholders
        if grep -q "{API_KEY}" "$INSTALL_DIR/config.php"; then
            print_warning "config.php still has placeholders (normal before setup)"
        else
            print_success "config.php appears to be configured"
        fi
    else
        print_error "config.php not found"
        return 1
    fi
}

# Test 8: Check SSL certificate
test_ssl() {
    print_test "Checking SSL certificate..."
    
    if [ -f /etc/nginx/sites-available/mirza_pro ]; then
        DOMAIN=$(grep -oP 'server_name\s+\K[^;]+' /etc/nginx/sites-available/mirza_pro | head -1 | tr -d ' ')
        
        if [ -n "$DOMAIN" ] && [ "$DOMAIN" != "_" ]; then
            print_success "Domain configured: $DOMAIN"
            
            if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
                print_success "SSL certificate exists for $DOMAIN"
                
                # Check certificate expiry
                EXPIRY=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/$DOMAIN/cert.pem" 2>/dev/null | cut -d= -f2)
                if [ -n "$EXPIRY" ]; then
                    print_success "Certificate expires: $EXPIRY"
                fi
            else
                print_warning "SSL certificate not found for $DOMAIN"
            fi
        else
            print_warning "No domain configured (using IP)"
        fi
    fi
}

# Test 9: Check file permissions
test_permissions() {
    print_test "Checking file permissions..."
    
    if [ -d "$INSTALL_DIR" ]; then
        OWNER=$(stat -c '%U' "$INSTALL_DIR" 2>/dev/null || stat -f '%Su' "$INSTALL_DIR" 2>/dev/null)
        if [ "$OWNER" = "www-data" ]; then
            print_success "Installation directory owned by www-data"
        else
            print_warning "Installation directory owned by $OWNER (expected www-data)"
        fi
    fi
}

# Test 10: Check CLI tool
test_cli_tool() {
    print_test "Checking CLI tool..."
    
    if [ -f /usr/local/bin/mirza ]; then
        print_success "CLI tool installed at /usr/local/bin/mirza"
        if [ -x /usr/local/bin/mirza ]; then
            print_success "CLI tool is executable"
        else
            print_error "CLI tool is not executable"
            return 1
        fi
    else
        print_warning "CLI tool not found (may need to run installer)"
    fi
}

# Test 11: Check logs
test_logs() {
    print_test "Checking log files..."
    
    if [ -f /var/log/mirza_bot.log ]; then
        print_success "Bot log file exists"
        LOG_SIZE=$(stat -c%s /var/log/mirza_bot.log 2>/dev/null || stat -f%z /var/log/mirza_bot.log 2>/dev/null)
        if [ "$LOG_SIZE" -gt 0 ]; then
            print_success "Bot log has content ($LOG_SIZE bytes)"
            echo "Last 5 lines of bot log:"
            tail -n 5 /var/log/mirza_bot.log | sed 's/^/  /'
        else
            print_warning "Bot log is empty (normal if bot hasn't started)"
        fi
    else
        print_warning "Bot log file not found (normal if bot hasn't started)"
    fi
}

# Test 12: Check ports
test_ports() {
    print_test "Checking open ports..."
    
    # HTTP
    if netstat -tuln 2>/dev/null | grep -q ":80 " || ss -tuln 2>/dev/null | grep -q ":80 "; then
        HTTP_PROCESS=$(lsof -i :80 2>/dev/null | tail -n 1 | awk '{print $1}' || echo "unknown")
        print_success "Port 80 is open (used by: $HTTP_PROCESS)"
    else
        print_warning "Port 80 is not open"
    fi
    
    # HTTPS
    if netstat -tuln 2>/dev/null | grep -q ":443 " || ss -tuln 2>/dev/null | grep -q ":443 "; then
        HTTPS_PROCESS=$(lsof -i :443 2>/dev/null | tail -n 1 | awk '{print $1}' || echo "unknown")
        print_success "Port 443 is open (used by: $HTTPS_PROCESS)"
    else
        print_warning "Port 443 is not open (SSL may not be configured)"
    fi
}

# Test 13: Check PHP extensions
test_php_extensions() {
    print_test "Checking PHP extensions..."
    
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "json" "mbstring" "xml" "zip" "bcmath")
    MISSING=()
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -qi "^$ext$"; then
            print_success "PHP extension $ext is loaded"
        else
            print_error "PHP extension $ext is missing"
            MISSING+=("$ext")
        fi
    done
    
    if [ ${#MISSING[@]} -gt 0 ]; then
        print_error "Missing PHP extensions: ${MISSING[*]}"
        return 1
    fi
}

# Test 14: Check disk space
test_disk_space() {
    print_test "Checking disk space..."
    
    DISK_USAGE=$(df -h "$INSTALL_DIR" | tail -n 1 | awk '{print $5}' | sed 's/%//')
    AVAILABLE=$(df -h "$INSTALL_DIR" | tail -n 1 | awk '{print $4}')
    
    print_success "Disk usage: ${DISK_USAGE}% (${AVAILABLE} available)"
    
    if [ "$DISK_USAGE" -gt 90 ]; then
        print_warning "Disk usage is above 90%"
    fi
}

# Test 15: Check system resources
test_system_resources() {
    print_test "Checking system resources..."
    
    # RAM
    RAM_TOTAL=$(free -h | grep Mem | awk '{print $2}')
    RAM_USED=$(free -h | grep Mem | awk '{print $3}')
    RAM_AVAILABLE=$(free -h | grep Mem | awk '{print $7}')
    print_success "RAM: $RAM_USED used / $RAM_TOTAL total ($RAM_AVAILABLE available)"
    
    # CPU
    CPU_LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    print_success "CPU Load: $CPU_LOAD"
}

# Main test execution
main() {
    clear
    print_header
    
    TESTS_PASSED=0
    TESTS_FAILED=0
    TESTS_WARNINGS=0
    
    # Run all tests
    test_installation_dir && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_services && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_bot_process && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_database && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_nginx_config && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_web_panel && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_config_file && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_ssl && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_permissions && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_cli_tool && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_logs && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_ports && ((TESTS_PASSED++)) || ((TESTS_WARNINGS++))
    echo ""
    
    test_php_extensions && ((TESTS_PASSED++)) || ((TESTS_FAILED++))
    echo ""
    
    test_disk_space && ((TESTS_PASSED++)) || true
    echo ""
    
    test_system_resources && ((TESTS_PASSED++)) || true
    echo ""
    
    # Summary
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  Test Summary${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}Passed:${NC} $TESTS_PASSED"
    echo -e "${YELLOW}Warnings:${NC} $TESTS_WARNINGS"
    echo -e "${RED}Failed:${NC} $TESTS_FAILED"
    echo ""
    echo "Full test log saved to: $LOG_FILE"
    echo ""
    
    if [ $TESTS_FAILED -eq 0 ]; then
        print_success "All critical tests passed!"
        exit 0
    else
        print_error "Some tests failed. Check the log for details."
        exit 1
    fi
}

main "$@"

