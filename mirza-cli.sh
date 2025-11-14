#!/bin/bash
# Mirza Pro - Enhanced CLI Management Tool
# Beautiful interactive menu with full functionality

set -euo pipefail

PROGRAM="mirza_bot"
INSTALL_DIR="/var/www/mirza_pro"
PHP_VERSION="8.2"
LOG_FILE="/var/log/${PROGRAM}.log"

# Enhanced color palette - Cyberpunk theme
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
WHITE='\033[1;37m'
BOLD='\033[1m'
NC='\033[0m'

# Cyberpunk colors
NEON_CYAN='\033[1;96m'
NEON_PINK='\033[1;95m'
NEON_GREEN='\033[1;92m'
NEON_YELLOW='\033[1;93m'
DARK_BG='\033[48;5;232m'

as_root() { 
    if [ "$EUID" -ne 0 ]; then 
        echo -e "${RED}${BOLD}⚠  Run as root: ${CYAN}sudo mirza${NC}"
        exit 1
    fi
}

ensure_install_dir() { 
    [ -f "$INSTALL_DIR/index.php" ] || INSTALL_DIR="/var/www/mirza_pro_latest"
}

# DB helpers
DB_NAME=""; DB_USER=""; DB_PASSWORD=""; DB_HOST="localhost"
load_db_creds() {
  if [ -f /root/.mirza_db_credentials ]; then . /root/.mirza_db_credentials || true; fi
  if [ -f "$INSTALL_DIR/config.php" ]; then
    DB_NAME=${DB_NAME:-$(grep -Po "^\$dbname\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
    DB_USER=${DB_USER:-$(grep -Po "^\$usernamedb\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
    DB_PASSWORD=${DB_PASSWORD:-$(grep -Po "^\$passworddb\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
  fi
  if [ -z "${DB_NAME:-}" ] || [ -z "${DB_USER:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
    if [ -f "$INSTALL_DIR/webpanel/.db_credentials.json" ]; then
      DB_NAME=${DB_NAME:-$(grep -Po '"db_name"\s*:\s*"\K[^"]+' "$INSTALL_DIR/webpanel/.db_credentials.json" | head -1 || true)}
      DB_USER=${DB_USER:-$(grep -Po '"db_user"\s*:\s*"\K[^"]+' "$INSTALL_DIR/webpanel/.db_credentials.json" | head -1 || true)}
      DB_PASSWORD=${DB_PASSWORD:-$(grep -Po '"db_password"\s*:\s*"\K[^"]+' "$INSTALL_DIR/webpanel/.db_credentials.json" | head -1 || true)}
      DB_HOST=${DB_HOST:-$(grep -Po '"db_host"\s*:\s*"\K[^"]+' "$INSTALL_DIR/webpanel/.db_credentials.json" | head -1 || echo localhost)}
    fi
  fi
}

mysql_exec() {
  local sql="$1"
  if [ -n "${DB_USER:-}" ] && [ -n "${DB_PASSWORD:-}" ] && [ -n "${DB_NAME:-}" ]; then
    mysql -h "${DB_HOST:-localhost}" -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "$sql" 2>/dev/null; return $?
  fi
  if [ -f /etc/mysql/debian.cnf ]; then
    if [ -n "${DB_NAME:-}" ]; then mysql --defaults-file=/etc/mysql/debian.cnf -D "$DB_NAME" -e "$sql" 2>/dev/null; else mysql --defaults-file=/etc/mysql/debian.cnf -e "$sql" 2>/dev/null; fi; return $?
  fi
  echo "No MySQL credentials available"; return 1
}

mysql_admin_exec() {
  local sql="$1"
  if [ -f /root/.mysql_root_password ]; then
    local RP; RP=$(cat /root/.mysql_root_password 2>/dev/null || true)
    if [ -n "$RP" ] && [ "$RP" != "EXISTING_MYSQL=true" ]; then mysql -uroot -p"$RP" -e "$sql" 2>/dev/null; return $?; fi
  fi
  if [ -f /etc/mysql/debian.cnf ]; then mysql --defaults-file=/etc/mysql/debian.cnf -e "$sql" 2>/dev/null; return $?; fi
  if sudo mysql -e "SELECT 1;" >/dev/null 2>&1; then sudo mysql -e "$sql" 2>/dev/null; return $?; fi
  echo "No admin MySQL access available"; return 1
}

print_header() {
    clear
    echo -e "${NEON_CYAN}"
    echo "╔═══════════════════════════════════════════════════════════════╗"
    echo "║                                                               ║"
    echo "║     ${NEON_PINK}╔═╗╦╔╦╗╔═╗╔═╗     ${NEON_CYAN}╔═╗╦═╗╔═╗╔═╗${NEON_CYAN}                    ║"
    echo "║     ${NEON_PINK}║ ╦║║║║║╣ ║ ║     ${NEON_CYAN}╠═╝╠╦╝║╣ ╚═╗${NEON_CYAN}                    ║"
    echo "║     ${NEON_PINK}╚═╝╩╩ ╩╚═╝╚═╝     ${NEON_CYAN}╩  ╩╚╝╚═╝╚═╝${NEON_CYAN}                    ║"
    echo "║                                                               ║"
    echo "║              ${WHITE}${BOLD}Command Line Interface${NC}${NEON_CYAN}                    ║"
    echo "╚═══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_status_box() {
    local status=$(supervisorctl status "$PROGRAM" 2>/dev/null | awk '{print $2}' || echo "UNKNOWN")
    local status_color=$RED
    local status_icon="●"
    
    case "$status" in
        RUNNING) status_color=$NEON_GREEN; status_icon="▶" ;;
        STOPPED) status_color=$RED; status_icon="■" ;;
        STARTING|STOPPING) status_color=$YELLOW; status_icon="⏳" ;;
        *) status_color=$RED; status_icon="⚠" ;;
    esac
    
    http_port=$(grep -oP 'listen \K[0-9]+' /etc/nginx/sites-available/mirza_pro 2>/dev/null | head -1 || echo 80)
    srv_ip=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")
    
    echo -e "${CYAN}┌─────────────────────────────────────────────────────────────┐${NC}"
    echo -e "${CYAN}│${NC} ${BOLD}Status:${NC} ${status_color}${status_icon} ${status}${NC}"
    echo -e "${CYAN}│${NC} ${BOLD}Web Panel:${NC} ${NEON_CYAN}http://${srv_ip}${http_port:+:$http_port}/webpanel/${NC}"
    echo -e "${CYAN}└─────────────────────────────────────────────────────────────┘${NC}"
    echo ""
}

print_menu() {
    echo -e "${NEON_PINK}${BOLD}  MAIN MENU${NC}"
    echo ""
    echo -e "${CYAN}  ${BOLD}Bot Control:${NC}"
    echo -e "    ${GREEN}1)${NC} View bot status"
    echo -e "    ${GREEN}2)${NC} Start bot"
    echo -e "    ${GREEN}3)${NC} Stop bot"
    echo -e "    ${GREEN}4)${NC} Restart bot"
    echo -e "    ${GREEN}5)${NC} View bot logs (live)"
    echo ""
    echo -e "${CYAN}  ${BOLD}System Management:${NC}"
    echo -e "    ${GREEN}6)${NC} Update code (git pull + restart)"
    echo -e "    ${GREEN}7)${NC} Reload PHP-FPM & Nginx"
    echo -e "    ${GREEN}8)${NC} Re-run setup wizard"
    echo ""
    echo -e "${CYAN}  ${BOLD}Credentials Management:${NC}"
    echo -e "    ${GREEN}9)${NC} Change web panel admin username/password"
    echo -e "    ${GREEN}10)${NC} Change database password"
    echo -e "    ${GREEN}11)${NC} View current credentials"
    echo ""
    echo -e "${CYAN}  ${BOLD}Database:${NC}"
    echo -e "    ${GREEN}12)${NC} Ensure DB user/permissions"
    echo ""
    echo -e "${CYAN}  ${BOLD}Uninstall:${NC}"
    echo -e "    ${GREEN}13)${NC} Uninstall (keep database)"
    echo -e "    ${GREEN}14)${NC} Uninstall (purge database)"
    echo ""
    echo -e "${RED}  ${GREEN}0)${NC} Exit"
    echo ""
}

status() { 
    print_header
    print_status_box
    echo -e "${CYAN}Detailed Status:${NC}"
    supervisorctl status "$PROGRAM" || true
    echo ""
    read -rp "Press Enter to continue..." _
}

start_bot() { 
    echo -e "${YELLOW}Starting bot...${NC}"
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl start "$PROGRAM" || true
    sleep 2
    status
}

stop_bot() { 
    echo -e "${YELLOW}Stopping bot...${NC}"
    supervisorctl stop "$PROGRAM" || true
    sleep 1
    status
}

restart_bot() { 
    echo -e "${YELLOW}Restarting bot...${NC}"
    supervisorctl restart "$PROGRAM" || true
    sleep 2
    status
}

logs() { 
    clear
    echo -e "${NEON_CYAN}${BOLD}Bot Logs (Press Ctrl+C to exit)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    tail -n 200 -f "$LOG_FILE" 2>/dev/null || echo "Log file not found: $LOG_FILE"
}

reload_web() { 
    echo -e "${YELLOW}Reloading web services...${NC}"
    nginx -t && systemctl reload nginx || systemctl restart nginx || true
    systemctl reload "php${PHP_VERSION}-fpm" || systemctl restart "php${PHP_VERSION}-fpm" || true
    echo -e "${GREEN}✓ Web services reloaded${NC}"
    sleep 1
}

setup_flag() { 
    touch "$INSTALL_DIR/webpanel/.needs_setup" && chown www-data:www-data "$INSTALL_DIR/webpanel/.needs_setup"
    echo -e "${GREEN}✓ Setup wizard enabled${NC}"
    echo -e "${CYAN}Access: http://$(hostname -I | awk '{print $1}')/webpanel/setup.php${NC}"
    sleep 2
}

update_code() {
    ensure_install_dir
    echo -e "${YELLOW}Updating code...${NC}"
    if [ -d "$INSTALL_DIR/.git" ]; then
        git -C "$INSTALL_DIR" fetch --all --prune
        git -C "$INSTALL_DIR" reset --hard origin/main || git -C "$INSTALL_DIR" pull --ff-only || true
    fi
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    supervisorctl reread || true
    supervisorctl update || true
    restart_bot || true
    reload_web || true
    echo -e "${GREEN}✓ Update complete${NC}"
    sleep 2
}

uninstall_keep_db() {
    ensure_install_dir
    echo -e "${RED}${BOLD}⚠ WARNING: This will remove all application files!${NC}"
    read -rp "Type 'yes' to confirm: " confirm
    if [ "$confirm" != "yes" ]; then
        echo "Cancelled."
        sleep 1
        return
    fi
    stop_bot || true
    rm -rf "$INSTALL_DIR"
    rm -f /etc/supervisor/conf.d/mirza_bot.conf
    supervisorctl reread || true
    supervisorctl update || true
    nginx -t && systemctl reload nginx || true
    echo -e "${GREEN}✓ Removed app files. Database preserved.${NC}"
    sleep 2
}

uninstall_purge_db() {
    ensure_install_dir
    load_db_creds
    echo -e "${RED}${BOLD}⚠ WARNING: This will remove ALL files AND database!${NC}"
    read -rp "Type 'DELETE ALL' to confirm: " confirm
    if [ "$confirm" != "DELETE ALL" ]; then
        echo "Cancelled."
        sleep 1
        return
    fi
    stop_bot || true
    if [ -n "${DB_NAME:-}" ]; then
        echo -e "${YELLOW}Dropping database...${NC}"
        mysql_admin_exec "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || true
    fi
    rm -rf "$INSTALL_DIR"
    rm -f /etc/supervisor/conf.d/mirza_bot.conf
    supervisorctl reread || true
    supervisorctl update || true
    nginx -t && systemctl reload nginx || true
    echo -e "${GREEN}✓ Removed app and dropped database ${DB_NAME:-<unknown>}${NC}"
    sleep 2
}

reset_admin() {
    as_root
    ensure_install_dir
    load_db_creds
    
    local new_user="" new_pass=""
    
    # Parse command line args if provided
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --username) new_user="$2"; shift 2;;
            --password) new_pass="$2"; shift 2;;
            *) shift;;
        esac
    done
    
    # Interactive mode
    if [ -z "$new_user" ] && [ -z "$new_pass" ]; then
        echo -e "${CYAN}Change Web Panel Admin Credentials${NC}"
        echo ""
        read -rp "New admin username [admin]: " new_user
        new_user=${new_user:-admin}
        read -rsp "New admin password: " new_pass
        echo ""
        if [ -z "$new_pass" ]; then
            echo -e "${RED}Password cannot be empty${NC}"
            sleep 2
            return
        fi
    fi
    
    [ -z "$new_user" ] && new_user="admin"
    [ -z "$new_pass" ] && { echo -e "${RED}Password required${NC}"; exit 1; }
    
    echo -e "${YELLOW}Updating admin credentials...${NC}"
    
    local HASH
    HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$new_pass" 2>/dev/null || echo "")
    
    if [ -z "$HASH" ]; then
        echo -e "${RED}Failed to hash password${NC}"
        sleep 2
        return
    fi
    
    local has_username has_password has_u_legacy has_p_legacy
    has_username=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'username';" 2>/dev/null | wc -l || echo "0")
    has_password=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'password';" 2>/dev/null | wc -l || echo "0")
    has_u_legacy=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'username_admin';" 2>/dev/null | wc -l || echo "0")
    has_p_legacy=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'password_admin';" 2>/dev/null | wc -l || echo "0")
    
    local admin_id
    admin_id=$(mysql_exec "SELECT id_admin FROM admin WHERE username='${new_user}' OR username_admin='${new_user}' ORDER BY id_admin ASC LIMIT 1;" 2>/dev/null | tail -n +2 | tr -d ' \t\r\n' || echo "")
    
    if [ -n "$admin_id" ]; then
        local update_sql="UPDATE admin SET "
        [ "$has_username" -gt 0 ] && update_sql+="username='${new_user}',"
        [ "$has_password" -gt 0 ] && update_sql+="password='${HASH}',"
        [ "$has_u_legacy" -gt 0 ] && update_sql+="username_admin='${new_user}',"
        [ "$has_p_legacy" -gt 0 ] && update_sql+="password_admin='${new_pass}',"
        update_sql+="rule='administrator' WHERE id_admin=${admin_id};"
        mysql_exec "$update_sql" || true
    else
        local insert_cols=""
        local insert_vals=""
        [ "$has_username" -gt 0 ] && insert_cols+="username," && insert_vals+="'${new_user}',"
        [ "$has_password" -gt 0 ] && insert_cols+="password," && insert_vals+="'${HASH}',"
        [ "$has_u_legacy" -gt 0 ] && insert_cols+="username_admin," && insert_vals+="'${new_user}',"
        [ "$has_p_legacy" -gt 0 ] && insert_cols+="password_admin," && insert_vals+="'${new_pass}',"
        insert_cols+="rule"
        insert_vals+="'administrator'"
        mysql_exec "INSERT INTO admin ($insert_cols) VALUES ($insert_vals);" || true
    fi
    
    echo -e "${GREEN}✓ Admin credentials updated${NC}"
    echo -e "${CYAN}  Username: ${new_user}${NC}"
    sleep 2
}

change_db_password() {
    as_root
    ensure_install_dir
    load_db_creds
    
    if [ -z "${DB_NAME:-}" ] || [ -z "${DB_USER:-}" ]; then
        echo -e "${RED}Database credentials not found${NC}"
        sleep 2
        return
    fi
    
    echo -e "${CYAN}Change Database Password${NC}"
    echo ""
    echo -e "${YELLOW}Current database: ${DB_NAME}${NC}"
    echo -e "${YELLOW}Current user: ${DB_USER}${NC}"
    echo ""
    
    read -rsp "New database password (leave empty to generate): " new_pass
    echo ""
    
    if [ -z "$new_pass" ]; then
        new_pass=$(openssl rand -base64 24)
        echo -e "${GREEN}Generated password: ${new_pass}${NC}"
    fi
    
    echo -e "${YELLOW}Updating database password...${NC}"
    
    mysql_admin_exec "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${new_pass}'; FLUSH PRIVILEGES;" || {
        echo -e "${RED}Failed to update password${NC}"
        sleep 2
        return
    }
    
    # Update saved credentials
    cat > /root/.mirza_db_credentials <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${new_pass}
EOF
    chmod 600 /root/.mirza_db_credentials
    
    cat > "$INSTALL_DIR/webpanel/.db_credentials.json" <<JSON
{"db_host":"localhost","db_name":"${DB_NAME}","db_user":"${DB_USER}","db_password":"${new_pass}"}
JSON
    chown www-data:www-data "$INSTALL_DIR/webpanel/.db_credentials.json" 2>/dev/null || true
    chmod 600 "$INSTALL_DIR/webpanel/.db_credentials.json" 2>/dev/null || true
    
    echo -e "${GREEN}✓ Database password updated${NC}"
    echo -e "${CYAN}  New password saved to credential files${NC}"
    sleep 2
}

view_credentials() {
    as_root
    ensure_install_dir
    load_db_creds
    
    print_header
    echo -e "${NEON_PINK}${BOLD}  CURRENT CREDENTIALS${NC}"
    echo ""
    
    echo -e "${CYAN}Database Credentials:${NC}"
    if [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ] && [ -n "${DB_PASSWORD:-}" ]; then
        echo -e "  ${GREEN}Database:${NC}     ${DB_NAME}"
        echo -e "  ${GREEN}User:${NC}         ${DB_USER}"
        echo -e "  ${GREEN}Password:${NC}     ${DB_PASSWORD}"
    else
        echo -e "  ${RED}Not configured${NC}"
    fi
    echo ""
    
    # MySQL root password
    if [ -f /root/.mysql_root_password ]; then
        MYSQL_ROOT=$(cat /root/.mysql_root_password 2>/dev/null || echo "")
        if [ "$MYSQL_ROOT" != "EXISTING_MYSQL=true" ]; then
            echo -e "${CYAN}MySQL Root Password:${NC}"
            echo -e "  ${GREEN}Password:${NC}     ${MYSQL_ROOT}"
        else
            echo -e "${CYAN}MySQL:${NC}"
            echo -e "  ${YELLOW}Using existing installation${NC}"
        fi
    fi
    echo ""
    
    # Admin credentials from database
    echo -e "${CYAN}Web Panel Admin:${NC}"
    local admin_info=$(mysql_exec "SELECT username, username_admin FROM admin LIMIT 1;" 2>/dev/null | tail -n +2 || echo "")
    if [ -n "$admin_info" ]; then
        local admin_user=$(echo "$admin_info" | awk '{print $1}')
        echo -e "  ${GREEN}Username:${NC}     ${admin_user}"
        echo -e "  ${YELLOW}Password:${NC}     (hashed in database)"
    else
        echo -e "  ${RED}No admin found${NC}"
    fi
    echo ""
    
    read -rp "Press Enter to continue..." _
}

ensure_db_user() {
    as_root
    ensure_install_dir
    load_db_creds
    
    echo -e "${CYAN}Ensure Database User${NC}"
    echo ""
    
    if [ -z "${DB_NAME:-}" ]; then
        read -rp "Database name: " DB_NAME
    else
        echo -e "${YELLOW}Database: ${DB_NAME}${NC}"
    fi
    
    if [ -z "${DB_USER:-}" ]; then
        read -rp "Database user: " DB_USER
    else
        echo -e "${YELLOW}User: ${DB_USER}${NC}"
    fi
    
    read -rsp "New password (leave empty to generate): " DB_PASSWORD
    echo ""
    [ -z "$DB_PASSWORD" ] && DB_PASSWORD=$(openssl rand -base64 24)
    
    echo -e "${YELLOW}Creating/updating database user...${NC}"
    
    mysql_admin_exec "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
        CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; \
        ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; \
        GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" || {
        echo -e "${RED}Failed to create/update user${NC}"
        sleep 2
        return
    }
    
    # Persist credentials
    install -d -m 750 -o www-data -g www-data "$INSTALL_DIR/webpanel" 2>/dev/null || true
    cat > /root/.mirza_db_credentials <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
EOF
    chmod 600 /root/.mirza_db_credentials
    
    cat > "$INSTALL_DIR/webpanel/.db_credentials.json" <<JSON
{"db_host":"localhost","db_name":"${DB_NAME}","db_user":"${DB_USER}","db_password":"${DB_PASSWORD}"}
JSON
    chown www-data:www-data "$INSTALL_DIR/webpanel/.db_credentials.json" 2>/dev/null || true
    chmod 600 "$INSTALL_DIR/webpanel/.db_credentials.json" 2>/dev/null || true
    
    echo -e "${GREEN}✓ Database user ensured and credentials saved${NC}"
    sleep 2
}

menu() {
    as_root
    ensure_install_dir
    
    while true; do
        print_header
        print_status_box
        print_menu
        
        read -rp "$(echo -e ${NEON_CYAN}Select\ option:\ ${NC})" opt
        
        case "$opt" in
            1) status ;;
            2) start_bot ;;
            3) stop_bot ;;
            4) restart_bot ;;
            5) logs ;;
            6) update_code ;;
            7) reload_web ;;
            8) setup_flag ;;
            9) reset_admin ;;
            10) change_db_password ;;
            11) view_credentials ;;
            12) ensure_db_user ;;
            13) uninstall_keep_db ;;
            14) uninstall_purge_db ;;
            0) 
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

usage() {
    cat <<USAGE
${NEON_CYAN}${BOLD}Mirza Pro CLI${NC} - Enhanced Management Tool

${CYAN}Usage:${NC} mirza <command> [args]

${CYAN}Bot Control:${NC}
  status              Show bot status
  start               Start bot
  stop                Stop bot
  restart             Restart bot
  logs                View live bot logs

${CYAN}System:${NC}
  update              Pull latest code, restart bot, reload services
  setup               Re-run setup wizard
  reload-web          Reload PHP-FPM and Nginx

${CYAN}Credentials:${NC}
  reset-admin [--username U] [--password P]
                      Change web panel admin credentials
  change-db-password   Change database password
  view-creds           View all saved credentials

${CYAN}Database:${NC}
  db-ensure           Create/ensure database user and permissions

${CYAN}Uninstall:${NC}
  uninstall [--purge-db]
                      Remove app files (optionally drop database)

${CYAN}Interactive:${NC}
  menu                Open interactive menu (default)

USAGE
}

main() {
    ensure_install_dir
    load_db_creds
    local cmd=${1:-menu}
    shift || true
    
    case "$cmd" in
        status) status ;;
        start) start_bot ;;
        stop) stop_bot ;;
        restart) restart_bot ;;
        logs) logs ;;
        update) update_code ;;
        setup) setup_flag ;;
        reload-web) reload_web ;;
        reset-admin) reset_admin "$@" ;;
        change-db-password) change_db_password ;;
        view-creds) view_credentials ;;
        db-ensure) ensure_db_user ;;
        uninstall) 
            if [ "${1:-}" = "--purge-db" ]; then 
                uninstall_purge_db
            else 
                uninstall_keep_db
            fi
            ;;
        menu|'') menu ;;
        -h|--help|help) usage ;;
        *)
            echo -e "${RED}Unknown command: $cmd${NC}"
            usage
            exit 1
            ;;
    esac
}

main "$@"
