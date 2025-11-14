#!/bin/bash
# Quick fix script to update config.php with bot credentials
# Usage: ./quick-fix-config.sh

CONFIG_FILE="/var/www/mirza_pro/config.php"
BOT_TOKEN="8265630669:AAGXMO7JYPSafykkSnzn9wj5-Rg6gqrn7HI"
ADMIN_ID="421290652"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: config.php not found at $CONFIG_FILE"
    exit 1
fi

# Backup config.php
cp "$CONFIG_FILE" "${CONFIG_FILE}.backup.$(date +%Y%m%d_%H%M%S)"

# Update config.php
sed -i "s/\$APIKEY = '[^']*';/\$APIKEY = '${BOT_TOKEN}';/g" "$CONFIG_FILE"
sed -i "s/\$adminnumber = '[^']*';/\$adminnumber = '${ADMIN_ID}';/g" "$CONFIG_FILE"

echo "✓ config.php updated successfully"
echo "✓ Bot token: ${BOT_TOKEN:0:20}..."
echo "✓ Admin ID: $ADMIN_ID"

# Restart bot
echo ""
echo "Restarting bot..."
supervisorctl restart mirza_bot
sleep 2
supervisorctl status mirza_bot

echo ""
echo "Done! Check bot status above."

