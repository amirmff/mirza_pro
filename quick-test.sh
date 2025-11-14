#!/bin/bash
# Quick Test Script - Run this first
# Tests basic functionality

INSTALL_DIR="/var/www/mirza_pro"

echo "=== Quick Test ==="
echo ""

# Test 1: Installation exists
if [ -d "$INSTALL_DIR" ]; then
    echo "✓ Installation directory exists"
else
    echo "✗ Installation directory not found"
    exit 1
fi

# Test 2: Services running
echo ""
echo "Checking services..."
systemctl is-active --quiet nginx && echo "✓ Nginx running" || echo "✗ Nginx not running"
systemctl is-active --quiet mysql && echo "✓ MySQL running" || echo "✗ MySQL not running"
systemctl is-active --quiet supervisor && echo "✓ Supervisor running" || echo "✗ Supervisor not running"

# Test 3: Bot process
echo ""
echo "Checking bot process..."
if supervisorctl status mirza_bot 2>/dev/null | grep -q RUNNING; then
    echo "✓ Bot is running"
else
    echo "⚠ Bot is not running (may need setup)"
fi

# Test 4: Web panel accessible
echo ""
echo "Checking web panel..."
if [ -f "$INSTALL_DIR/webpanel/index.php" ]; then
    echo "✓ Web panel files exist"
else
    echo "✗ Web panel files not found"
fi

# Test 5: Database
echo ""
echo "Checking database..."
if [ -f /root/.mirza_db_credentials ]; then
    source /root/.mirza_db_credentials
    if mysql -h localhost -u"$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1; then
        echo "✓ Database connection works"
    else
        echo "✗ Database connection failed"
    fi
else
    echo "⚠ Database credentials not found"
fi

echo ""
echo "=== Quick Test Complete ==="
echo "For detailed testing, run: ./test-installation.sh"

