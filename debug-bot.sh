#!/bin/bash
# Bot Debugging Script
# Helps diagnose bot issues

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

INSTALL_DIR="/var/www/mirza_pro"

print_header() {
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  Mirza Pro - Bot Debugging Tool${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Check bot status
check_bot_status() {
    echo -e "${BLUE}Bot Process Status:${NC}"
    supervisorctl status mirza_bot || echo "Bot not configured in supervisor"
    echo ""
}

# Check bot logs
check_bot_logs() {
    echo -e "${BLUE}Recent Bot Logs (last 50 lines):${NC}"
    if [ -f /var/log/mirza_bot.log ]; then
        tail -n 50 /var/log/mirza_bot.log
    else
        echo "Log file not found"
    fi
    echo ""
}

# Check webhook
check_webhook() {
    echo -e "${BLUE}Checking Telegram Webhook:${NC}"
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        APIKEY=$(grep -oP "^\$APIKEY\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || echo "")
        
        if [ -n "$APIKEY" ] && [ "$APIKEY" != "{API_KEY}" ]; then
            echo "Bot token found, checking webhook..."
            WEBHOOK_INFO=$(curl -s "https://api.telegram.org/bot${APIKEY}/getWebhookInfo")
            echo "$WEBHOOK_INFO" | python3 -m json.tool 2>/dev/null || echo "$WEBHOOK_INFO"
        else
            echo "Bot token not configured yet"
        fi
    else
        echo "config.php not found"
    fi
    echo ""
}

# Check config.php
check_config() {
    echo -e "${BLUE}Checking config.php:${NC}"
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        echo "File exists: ✓"
        
        # Check for placeholders
        if grep -q "{API_KEY}" "$INSTALL_DIR/config.php"; then
            echo "API_KEY: Not configured (placeholder found)"
        else
            APIKEY=$(grep -oP "^\$APIKEY\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || echo "")
            if [ -n "$APIKEY" ]; then
                echo "API_KEY: Configured (${#APIKEY} characters)"
            fi
        fi
        
        if grep -q "{admin_number}" "$INSTALL_DIR/config.php"; then
            echo "Admin ID: Not configured (placeholder found)"
        else
            ADMIN_ID=$(grep -oP "^\$adminnumber\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || echo "")
            if [ -n "$ADMIN_ID" ]; then
                echo "Admin ID: Configured ($ADMIN_ID)"
            fi
        fi
        
        if grep -q "{domain_name}" "$INSTALL_DIR/config.php"; then
            echo "Domain: Not configured (placeholder found)"
        else
            DOMAIN=$(grep -oP "^\$domainhosts\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || echo "")
            if [ -n "$DOMAIN" ] && [ "$DOMAIN" != "_" ]; then
                echo "Domain: Configured ($DOMAIN)"
            fi
        fi
    else
        echo "File not found: ✗"
    fi
    echo ""
}

# Check database connection
check_database() {
    echo -e "${BLUE}Checking Database Connection:${NC}"
    
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials
        echo "Credentials file: ✓"
        echo "Database: $DB_NAME"
        echo "User: $DB_USER"
        
        if mysql -h localhost -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1; then
            echo "Connection: ✓ Success"
            
            # Check tables
            TABLE_COUNT=$(mysql -h localhost -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l)
            echo "Tables: $((TABLE_COUNT-1)) found"
        else
            echo "Connection: ✗ Failed"
        fi
    else
        echo "Credentials file: ✗ Not found"
    fi
    echo ""
}

# Test bot API
test_bot_api() {
    echo -e "${BLUE}Testing Bot API:${NC}"
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        APIKEY=$(grep -oP "^\$APIKEY\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || echo "")
        
        if [ -n "$APIKEY" ] && [ "$APIKEY" != "{API_KEY}" ]; then
            echo "Testing getMe endpoint..."
            RESPONSE=$(curl -s "https://api.telegram.org/bot${APIKEY}/getMe")
            
            if echo "$RESPONSE" | grep -q '"ok":true'; then
                echo "API Connection: ✓ Success"
                BOT_USERNAME=$(echo "$RESPONSE" | grep -oP '"username":"\K[^"]+' || echo "")
                if [ -n "$BOT_USERNAME" ]; then
                    echo "Bot Username: @$BOT_USERNAME"
                fi
            else
                echo "API Connection: ✗ Failed"
                echo "Response: $RESPONSE"
            fi
        else
            echo "Bot token not configured"
        fi
    fi
    echo ""
}

# Check supervisor config
check_supervisor() {
    echo -e "${BLUE}Checking Supervisor Configuration:${NC}"
    
    if [ -f /etc/supervisor/conf.d/mirza_bot.conf ]; then
        echo "Config file: ✓"
        echo "Contents:"
        cat /etc/supervisor/conf.d/mirza_bot.conf | sed 's/^/  /'
    else
        echo "Config file: ✗ Not found"
    fi
    echo ""
}

# Check PHP syntax
check_php_syntax() {
    echo -e "${BLUE}Checking PHP Syntax:${NC}"
    
    if [ -f "$INSTALL_DIR/index.php" ]; then
        if php -l "$INSTALL_DIR/index.php" >/dev/null 2>&1; then
            echo "index.php: ✓ Valid syntax"
        else
            echo "index.php: ✗ Syntax errors"
            php -l "$INSTALL_DIR/index.php"
        fi
    fi
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        if php -l "$INSTALL_DIR/config.php" >/dev/null 2>&1; then
            echo "config.php: ✓ Valid syntax"
        else
            echo "config.php: ✗ Syntax errors"
            php -l "$INSTALL_DIR/config.php"
        fi
    fi
    echo ""
}

# Main menu
main() {
    clear
    print_header
    
    while true; do
        echo -e "${CYAN}Select an option:${NC}"
        echo "  1) Check bot status"
        echo "  2) View bot logs"
        echo "  3) Check webhook"
        echo "  4) Check config.php"
        echo "  5) Check database"
        echo "  6) Test bot API"
        echo "  7) Check supervisor config"
        echo "  8) Check PHP syntax"
        echo "  9) Run all checks"
        echo "  0) Exit"
        echo ""
        read -p "Choice: " choice
        
        case $choice in
            1) check_bot_status ;;
            2) check_bot_logs ;;
            3) check_webhook ;;
            4) check_config ;;
            5) check_database ;;
            6) test_bot_api ;;
            7) check_supervisor ;;
            8) check_php_syntax ;;
            9)
                check_bot_status
                check_config
                check_database
                check_webhook
                test_bot_api
                check_supervisor
                check_php_syntax
                ;;
            0) exit 0 ;;
            *) echo "Invalid choice" ;;
        esac
        echo ""
        read -p "Press Enter to continue..."
        clear
        print_header
    done
}

main "$@"

