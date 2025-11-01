#!/bin/bash
# Mirza Pro - CLI management tool and interactive menu
set -euo pipefail

PROGRAM="mirza_bot"
INSTALL_DIR="/var/www/mirza_pro"
PHP_VERSION="8.2"
LOG_FILE="/var/log/${PROGRAM}.log"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'

as_root() { if [ "$EUID" -ne 0 ]; then echo -e "${RED}Run as root: sudo mirza ...${NC}"; exit 1; fi; }
ensure_install_dir() { [ -f "$INSTALL_DIR/index.php" ] || INSTALL_DIR="/var/www/mirza_pro_latest"; }

# DB helpers
DB_NAME=""; DB_USER=""; DB_PASSWORD=""; DB_HOST="localhost"
load_db_creds() {
  # 1) From saved installer creds
  if [ -f /root/.mirza_db_credentials ]; then . /root/.mirza_db_credentials || true; fi
  # 2) From config.php variables ($dbname, $usernamedb, $passworddb)
  if [ -f "$INSTALL_DIR/config.php" ]; then
    DB_NAME=${DB_NAME:-$(grep -Po "^\$dbname\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
    DB_USER=${DB_USER:-$(grep -Po "^\$usernamedb\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
    DB_PASSWORD=${DB_PASSWORD:-$(grep -Po "^\$passworddb\s*=\s*'\K[^']+" "$INSTALL_DIR/config.php" | head -1 || true)}
  fi
  # 3) From webpanel JSON (written by installer/setup)
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
    mysql -h "${DB_HOST:-localhost}" -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "$sql"; return $?
  fi
  if [ -f /etc/mysql/debian.cnf ]; then
    if [ -n "${DB_NAME:-}" ]; then mysql --defaults-file=/etc/mysql/debian.cnf -D "$DB_NAME" -e "$sql"; else mysql --defaults-file=/etc/mysql/debian.cnf -e "$sql"; fi; return $?
  fi
  echo "No MySQL credentials available"; return 1
}
# Admin-level MySQL execution (root/debian maintenance)
mysql_admin_exec() {
  local sql="$1"
  if [ -f /root/.mysql_root_password ]; then
    local RP; RP=$(cat /root/.mysql_root_password 2>/dev/null || true)
    if [ -n "$RP" ] && [ "$RP" != "EXISTING_MYSQL=true" ]; then mysql -uroot -p"$RP" -e "$sql"; return $?; fi
  fi
  if [ -f /etc/mysql/debian.cnf ]; then mysql --defaults-file=/etc/mysql/debian.cnf -e "$sql"; return $?; fi
  if sudo mysql -e "SELECT 1;" >/dev/null 2>&1; then sudo mysql -e "$sql"; return $?; fi
  echo "No admin MySQL access available"; return 1
}
ensure_db_user() {
  as_root; ensure_install_dir; load_db_creds
  if [ -z "${DB_NAME:-}" ]; then read -rp "DB name: " DB_NAME; fi
  if [ -z "${DB_USER:-}" ]; then read -rp "DB user: " DB_USER; fi
  if [ -z "${DB_PASSWORD:-}" ]; then read -rsp "New DB password (generated if empty): " DB_PASSWORD; echo; fi
  [ -z "$DB_PASSWORD" ] && DB_PASSWORD=$(openssl rand -base64 24)
  mysql_admin_exec "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
    CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; \
    ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; \
    GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" || true
  # Persist creds for app and setup wizard
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
  echo "DB ensured and credentials saved."
}

status() { supervisorctl status "$PROGRAM" || true; }
start_bot() { supervisorctl reread || true; supervisorctl update || true; supervisorctl start "$PROGRAM" || true; status; }
stop_bot() { supervisorctl stop "$PROGRAM" || true; status; }
restart_bot() { supervisorctl restart "$PROGRAM" || true; status; }
logs() { tail -n 200 -f "$LOG_FILE"; }

reload_web() { nginx -t && systemctl reload nginx || systemctl restart nginx || true; systemctl reload "php${PHP_VERSION}-fpm" || systemctl restart "php${PHP_VERSION}-fpm" || true; }

setup_flag() { touch "$INSTALL_DIR/webpanel/.needs_setup" && chown www-data:www-data "$INSTALL_DIR/webpanel/.needs_setup"; echo "Setup wizard ready: /webpanel/setup.php"; }

update_code() {
  ensure_install_dir
  echo "Updating code in $INSTALL_DIR ..."
  if [ -d "$INSTALL_DIR/.git" ]; then
    git -C "$INSTALL_DIR" fetch --all --prune
    git -C "$INSTALL_DIR" reset --hard origin/main || git -C "$INSTALL_DIR" pull --ff-only || true
  fi
  chown -R www-data:www-data "$INSTALL_DIR"
  chmod -R 755 "$INSTALL_DIR"
  supervisorctl reread || true; supervisorctl update || true
  restart_bot || true
  reload_web || true
  echo "Update complete."
}

uninstall_keep_db() {
  ensure_install_dir; stop_bot || true
  rm -rf "$INSTALL_DIR"
  rm -f /etc/supervisor/conf.d/mirza_bot.conf; supervisorctl reread || true; supervisorctl update || true
  nginx -t && systemctl reload nginx || true
  echo "Removed app files. Database preserved."
}

uninstall_purge_db() {
  ensure_install_dir; load_db_creds; stop_bot || true
  if [ -n "${DB_NAME:-}" ]; then mysql_exec "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || true; fi
  rm -rf "$INSTALL_DIR"
  rm -f /etc/supervisor/conf.d/mirza_bot.conf; supervisorctl reread || true; supervisorctl update || true
  nginx -t && systemctl reload nginx || true
  echo "Removed app and dropped DB ${DB_NAME:-<unknown>}."
}

reset_admin() {
  as_root; ensure_install_dir; load_db_creds
  local new_user="" new_pass=""; while [[ $# -gt 0 ]]; do case "$1" in --username) new_user="$2"; shift 2;; --password) new_pass="$2"; shift 2;; *) shift;; esac; done
  if [ -z "$new_user" ] && [ -z "$new_pass" ]; then read -rp "New admin username [admin]: " new_user; new_user=${new_user:-admin}; read -rsp "New admin password: " new_pass; echo; fi
  [ -z "$new_user" ] && new_user="admin"; [ -z "$new_pass" ] && { echo "Password required"; exit 1; }
  local HASH; HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$new_pass")
  local has_username has_password has_u_legacy has_p_legacy
  has_username=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'username';" 2>/dev/null | wc -l || true)
  has_password=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'password';" 2>/dev/null | wc -l || true)
  has_u_legacy=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'username_admin';" 2>/dev/null | wc -l || true)
  has_p_legacy=$(mysql_exec "SHOW COLUMNS FROM admin LIKE 'password_admin';" 2>/dev/null | wc -l || true)
  # Detect existing row for this username to avoid duplicate key errors
  local admin_id
  admin_id=$(mysql_exec "SELECT id_admin FROM admin WHERE username='${new_user}' OR username_admin='${new_user}' ORDER BY id_admin ASC LIMIT 1;" 2>/dev/null | tail -n +2 | tr -d ' \t\r\n')
  if [ -n "$admin_id" ]; then
    mysql_exec "UPDATE admin SET \
      $( [ "$has_username" -gt 0 ] && echo "username='${new_user}'," ) \
      $( [ "$has_password" -gt 0 ] && echo "password='${HASH}'," ) \
      $( [ "$has_u_legacy" -gt 0 ] && echo "username_admin='${new_user}'," ) \
      $( [ "$has_p_legacy" -gt 0 ] && echo "password_admin='${new_pass}'," ) \
      rule='administrator' WHERE id_admin=${admin_id};" || true
  else
    mysql_exec "INSERT INTO admin \
      ($( [ "$has_username" -gt 0 ] && echo "username," )$( [ "$has_password" -gt 0 ] && echo "password," )$( [ "$has_u_legacy" -gt 0 ] && echo "username_admin," )$( [ "$has_p_legacy" -gt 0 ] && echo "password_admin," )rule) \
      VALUES ($( [ "$has_username" -gt 0 ] && echo "'${new_user}'," )$( [ "$has_password" -gt 0 ] && echo "'${HASH}'," )$( [ "$has_u_legacy" -gt 0 ] && echo "'${new_user}'," )$( [ "$has_p_legacy" -gt 0 ] && echo "'${new_pass}'," )'administrator');" || true
  fi
  echo "Admin updated. Username: ${new_user}"
}

menu() {
  as_root; ensure_install_dir
  while true; do
    clear
    echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║           Mirza Pro - Manager          ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"
    echo ""
    printf "${BLUE}Bot:${NC} "; status | sed 's/^/  /'
    http_port=$(grep -oP 'listen \K[0-9]+' /etc/nginx/sites-available/mirza_pro 2>/dev/null | head -1 || echo 80)
    srv_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    echo -e "${BLUE}URL:${NC} http://${srv_ip}${http_port:+:$http_port}/webpanel/"
    echo ""
    echo -e "${YELLOW}── Actions ─────────────────────────────${NC}"
    echo "  1) View bot status"
    echo "  2) Start bot"
    echo "  3) Stop bot"
    echo "  4) Restart bot"
    echo "  5) Tail bot logs"
    echo "  6) Update (pull + restart bot + reload PHP/Nginx)"
    echo "  7) Re-run setup wizard"
    echo "  8) Uninstall (keep database)"
    echo "  9) Uninstall (purge database)"
    echo " 10) Reset admin username/password"
    echo " 11) Reload PHP-FPM and Nginx"
    echo " 12) Ensure DB user/permissions"
    echo "  0) Exit"
    echo ""
    read -rp "Select: " opt
    case "$opt" in
      1) status; read -rp "Enter to continue..." _;;
      2) start_bot; read -rp "Enter to continue..." _;;
      3) stop_bot; read -rp "Enter to continue..." _;;
      4) restart_bot; read -rp "Enter to continue..." _;;
      5) logs;;
      6) update_code; read -rp "Enter to continue..." _;;
      7) setup_flag; read -rp "Enter to continue..." _;;
      8) uninstall_keep_db; read -rp "Enter to continue..." _;;
      9) uninstall_purge_db; read -rp "Enter to continue..." _;;
      10) reset_admin; read -rp "Enter to continue..." _;;
      11) reload_web; read -rp "Enter to continue..." _;;
      12) ensure_db_user; read -rp "Enter to continue..." _;;
      0) exit 0;;
      *) echo "Invalid"; sleep 1;;
    esac
  done
}

usage() { cat <<USAGE
mirza - Mirza Pro CLI
Usage: mirza <command> [args]
  status|start|stop|restart|logs
  update                  Pull latest, restart bot, reload PHP/Nginx
  setup                   Re-run setup wizard
  uninstall [--purge-db]  Remove app files (and optionally DB)
  reset-admin [--username U] [--password P]
  db-ensure               Create DB/user and grant perms (uses admin access)
  reload-web              Reload php-fpm and nginx
  menu                    Open interactive menu (default)
USAGE
}

main() {
  ensure_install_dir; load_db_creds
  local cmd=${1:-menu}; shift || true
  case "$cmd" in
    status) status;; start) start_bot;; stop) stop_bot;; restart) restart_bot;; logs) logs;;
    update) update_code;; setup) setup_flag;; reload-web) reload_web;;
    uninstall) if [ "${1:-}" = "--purge-db" ]; then uninstall_purge_db; else uninstall_keep_db; fi;;
    reset-admin) reset_admin "$@";; db-ensure) ensure_db_user;; menu|'') menu;; -h|--help|help) usage;;
    *) echo "Unknown command: $cmd"; usage; exit 1;;
  esac
}

main "$@"
