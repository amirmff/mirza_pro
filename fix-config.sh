#!/bin/bash

#########################################
# Mirza Pro - Config Fix Script
# Updates config.php with database credentials
#########################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

INSTALL_DIR="/var/www/mirza_pro"

echo -e "${YELLOW}Mirza Pro - Configuration Fix${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

# Check if credentials file exists
if [ ! -f /root/.mirza_db_credentials ]; then
    echo -e "${RED}Error: Database credentials file not found${NC}"
    echo "Please ensure Mirza Pro is installed correctly"
    exit 1
fi

# Load credentials
source /root/.mirza_db_credentials

echo -e "${YELLOW}Found database credentials:${NC}"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Check if config.php exists
if [ ! -f "$INSTALL_DIR/config.php" ]; then
    echo -e "${RED}Error: config.php not found at $INSTALL_DIR${NC}"
    exit 1
fi

# Backup original config
echo -e "${YELLOW}Creating backup...${NC}"
cp "$INSTALL_DIR/config.php" "$INSTALL_DIR/config.php.backup.$(date +%Y%m%d_%H%M%S)"
echo -e "${GREEN}✓ Backup created${NC}"
echo ""

# Update config.php
echo -e "${YELLOW}Updating config.php...${NC}"

# Escape special characters for sed
DB_NAME_ESCAPED=$(echo "$DB_NAME" | sed 's/[&/\\]/\\&/g')
DB_USER_ESCAPED=$(echo "$DB_USER" | sed 's/[&/\\]/\\&/g')
DB_PASSWORD_ESCAPED=$(echo "$DB_PASSWORD" | sed 's/[&/\\]/\\&/g')

# Use a temporary file for safe replacement
awk -v db="$DB_NAME" -v user="$DB_USER" -v pass="$DB_PASSWORD" '{
    gsub(/{database_name}/, db);
    gsub(/{username_db}/, user);
    gsub(/{password_db}/, pass);
    print
}' "$INSTALL_DIR/config.php" > "$INSTALL_DIR/config.php.tmp"

mv "$INSTALL_DIR/config.php.tmp" "$INSTALL_DIR/config.php"
chown www-data:www-data "$INSTALL_DIR/config.php"
chmod 640 "$INSTALL_DIR/config.php"

# Verify the changes
if grep -q "{database_name}" "$INSTALL_DIR/config.php"; then
    echo -e "${RED}✗ Failed to update config.php${NC}"
    exit 1
else
    echo -e "${GREEN}✓ config.php updated successfully${NC}"
fi

echo ""
echo -e "${GREEN}Configuration fixed!${NC}"
echo ""
echo -e "${YELLOW}Note: You still need to configure the bot settings:${NC}"
echo "  1. Telegram Bot Token (API_KEY)"
echo "  2. Admin User ID"
echo "  3. Domain name"
echo ""
echo "These can be configured via the web panel setup wizard"
echo ""
