#!/bin/bash

#########################################
# Mirza Pro - CLI Management Tool
# Interactive bot management interface
#########################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

INSTALL_DIR="/var/www/mirza_pro"
LOG_FILE="/var/log/mirza_pro_bot.log"

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then 
        echo -e "${RED}Please run as root (use sudo mirza)${NC}"
        exit 1
    fi
}

# Print header
print_header() {
    clear
    echo -e "${CYAN}"
    echo "╔════════════════════════════════════════╗"
    echo "║        Mirza Pro - CLI Manager        ║"
    echo "║         Bot Management Panel          ║"
    echo "╚════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
}

# Get bot status
get_bot_status() {
    if supervisorctl status mirza_pro_bot | grep -q "RUNNING"; then
        echo -e "${GREEN}● RUNNING${NC}"
    else
        echo -e "${RED}● STOPPED${NC}"
    fi
}

# Show main menu
show_menu() {
    print_header
    
    printf "\033[0;34mBot Status:\033[0m $(get_bot_status)\n"
    echo ""
    printf "\033[1;33m═══════════════════════════════════════\033[0m\n"
    echo ""
    printf "  \033[0;36m1.\033[0m Start Bot\n"
    printf "  \033[0;36m2.\033[0m Stop Bot\n"
    printf "  \033[0;36m3.\033[0m Restart Bot\n"
    printf "  \033[0;36m4.\033[0m View Bot Status\n"
    printf "  \033[0;36m5.\033[0m View Live Logs\n"
    printf "  \033[0;36m6.\033[0m View Last 50 Lines of Log\n"
    echo ""
    printf "\033[1;33m───────────────────────────────────────\033[0m\n"
    echo ""
    printf "  \033[0;36m7.\033[0m Database Info\n"
    printf "  \033[0;36m8.\033[0m Backup Database\n"
    printf "  \033[0;36m9.\033[0m View System Info\n"
    echo ""
    printf "\033[1;33m───────────────────────────────────────\033[0m\n"
    echo ""
    printf "  \033[0;36m10.\033[0m Update Bot (from GitHub)\n"
    printf "  \033[0;36m11.\033[0m Edit Configuration\n"
    printf "  \033[0;36m12.\033[0m Open Web Panel URL\n"
    echo ""
    printf "\033[1;33m───────────────────────────────────────\033[0m\n"
    echo ""
    printf "  \033[0;31m0.\033[0m Exit\n"
    echo ""
    printf "\033[1;33m═══════════════════════════════════════\033[0m\n"
    echo ""
    printf "Select option: "
}

# Start bot
start_bot() {
    echo -e "${YELLOW}Starting bot...${NC}"
    supervisorctl start mirza_pro_bot
    sleep 2
    echo -e "${GREEN}✓ Bot started${NC}"
    read -p "Press Enter to continue..."
}

# Stop bot
stop_bot() {
    echo -e "${YELLOW}Stopping bot...${NC}"
    supervisorctl stop mirza_pro_bot
    sleep 2
    echo -e "${GREEN}✓ Bot stopped${NC}"
    read -p "Press Enter to continue..."
}

# Restart bot
restart_bot() {
    echo -e "${YELLOW}Restarting bot...${NC}"
    supervisorctl restart mirza_pro_bot
    sleep 2
    echo -e "${GREEN}✓ Bot restarted${NC}"
    read -p "Press Enter to continue..."
}

# View status
view_status() {
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo -e "${BLUE}Bot Process Status${NC}"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    supervisorctl status mirza_pro_bot
    echo ""
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# View live logs
view_live_logs() {
    echo -e "${YELLOW}Showing live logs (Ctrl+C to exit)...${NC}"
    echo ""
    sleep 2
    tail -f "$LOG_FILE"
}

# View last logs
view_last_logs() {
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo -e "${BLUE}Last 50 Lines of Log${NC}"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    tail -n 50 "$LOG_FILE"
    echo ""
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Database info
database_info() {
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo -e "${BLUE}Database Information${NC}"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials
        echo -e "${GREEN}Database Name:${NC} $DB_NAME"
        echo -e "${GREEN}Database User:${NC} $DB_USER"
        echo -e "${GREEN}Password File:${NC} /root/.mirza_db_credentials"
        echo ""
        
        # Show database size
        MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password 2>/dev/null)
        if [ ! -z "$MYSQL_ROOT_PASSWORD" ]; then
            DB_SIZE=$(mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
            echo -e "${GREEN}Database Size:${NC} ${DB_SIZE} MB"
        fi
    else
        echo -e "${RED}No database credentials found${NC}"
    fi
    
    echo ""
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Backup database
backup_database() {
    echo -e "${YELLOW}Creating database backup...${NC}"
    
    if [ -f /root/.mirza_db_credentials ]; then
        source /root/.mirza_db_credentials
        MYSQL_ROOT_PASSWORD=$(cat /root/.mysql_root_password 2>/dev/null)
        
        BACKUP_DIR="$INSTALL_DIR/backups"
        mkdir -p "$BACKUP_DIR"
        
        BACKUP_FILE="$BACKUP_DIR/mirza_pro_$(date +%Y%m%d_%H%M%S).sql"
        
        if mysqldump -uroot -p"${MYSQL_ROOT_PASSWORD}" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
            gzip "$BACKUP_FILE"
            echo -e "${GREEN}✓ Backup created: ${BACKUP_FILE}.gz${NC}"
            echo -e "${BLUE}Size: $(du -h "${BACKUP_FILE}.gz" | cut -f1)${NC}"
        else
            echo -e "${RED}✗ Backup failed${NC}"
        fi
    else
        echo -e "${RED}No database credentials found${NC}"
    fi
    
    echo ""
    read -p "Press Enter to continue..."
}

# System info
system_info() {
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo -e "${BLUE}System Information${NC}"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    
    echo -e "${GREEN}OS:${NC} $(lsb_release -d | cut -f2)"
    echo -e "${GREEN}Kernel:${NC} $(uname -r)"
    echo -e "${GREEN}Uptime:${NC} $(uptime -p)"
    echo ""
    echo -e "${GREEN}CPU Usage:${NC}"
    top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print "  " 100 - $1"%"}'
    echo ""
    echo -e "${GREEN}Memory Usage:${NC}"
    free -h | awk '/^Mem:/ {print "  Used: " $3 " / Total: " $2}'
    echo ""
    echo -e "${GREEN}Disk Usage:${NC}"
    df -h / | awk 'NR==2 {print "  Used: " $3 " / Total: " $2 " (" $5 ")"}'
    echo ""
    echo -e "${GREEN}Install Directory:${NC} $INSTALL_DIR"
    echo -e "${GREEN}Log File:${NC} $LOG_FILE"
    
    echo ""
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Update bot
update_bot() {
    echo -e "${YELLOW}Updating bot from GitHub...${NC}"
    echo ""
    
    # Stop bot
    echo "Stopping bot..."
    supervisorctl stop mirza_pro_bot
    
    # Backup current version
    echo "Creating backup..."
    BACKUP_DIR="/root/mirza_backups"
    mkdir -p "$BACKUP_DIR"
    tar -czf "$BACKUP_DIR/mirza_pro_backup_$(date +%Y%m%d_%H%M%S).tar.gz" "$INSTALL_DIR" 2>/dev/null
    
    # Pull updates
    echo "Pulling updates..."
    cd "$INSTALL_DIR"
    git pull origin main
    
    # Restart bot
    echo "Starting bot..."
    supervisorctl start mirza_pro_bot
    
    echo ""
    echo -e "${GREEN}✓ Update complete${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Edit configuration
edit_config() {
    echo -e "${YELLOW}Opening configuration file...${NC}"
    echo ""
    
    if [ -f "$INSTALL_DIR/config.php" ]; then
        nano "$INSTALL_DIR/config.php"
        
        echo ""
        echo -e "${YELLOW}Restart bot to apply changes? [y/N]:${NC} "
        read -r REPLY
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            supervisorctl restart mirza_pro_bot
            echo -e "${GREEN}✓ Bot restarted${NC}"
        fi
    else
        echo -e "${RED}Configuration file not found${NC}"
        read -p "Press Enter to continue..."
    fi
}

# Open web panel
open_web_panel() {
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo -e "${BLUE}Web Panel Access${NC}"
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    
    SERVER_IP=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
    
    # Get HTTP port from Nginx config
    HTTP_PORT=$(grep -oP 'listen \K[0-9]+' /etc/nginx/sites-available/mirza_pro 2>/dev/null | head -1 || echo "80")
    
    if [ "$HTTP_PORT" = "80" ]; then
        WEB_URL="http://${SERVER_IP}/webpanel/"
    else
        WEB_URL="http://${SERVER_IP}:${HTTP_PORT}/webpanel/"
    fi
    
    echo ""
    echo -e "${GREEN}Web Panel URL:${NC}"
    echo -e "${CYAN}${WEB_URL}${NC}"
    echo ""
    echo -e "${YELLOW}Copy this URL to your browser${NC}"
    echo ""
    echo -e "${CYAN}════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Main loop
main() {
    check_root
    
    while true; do
        show_menu
        read -r choice
        
        case $choice in
            1) start_bot ;;
            2) stop_bot ;;
            3) restart_bot ;;
            4) view_status ;;
            5) view_live_logs ;;
            6) view_last_logs ;;
            7) database_info ;;
            8) backup_database ;;
            9) system_info ;;
            10) update_bot ;;
            11) edit_config ;;
            12) open_web_panel ;;
            0) 
                echo ""
                echo -e "${GREEN}Goodbye!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}Invalid option${NC}"
                sleep 1
                ;;
        esac
    done
}

# Run main
main "$@"
