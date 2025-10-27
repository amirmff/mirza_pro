#!/bin/bash

# Fix MySQL user authentication for Mirza Pro
DB_PASSWORD="QGMo+/1XnWeaOrMUlwgjGjouk9YU/OW4"

echo "Fixing MySQL user authentication..."

sudo mysql <<EOF
DROP USER IF EXISTS 'mirza_user'@'localhost';
CREATE USER 'mirza_user'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON mirza_pro.* TO 'mirza_user'@'localhost';
FLUSH PRIVILEGES;
SELECT User, Host, plugin FROM mysql.user WHERE User='mirza_user';
EOF

echo ""
echo "Testing database connection..."
mysql -u mirza_user -p"${DB_PASSWORD}" -e "SELECT DATABASE(); SHOW TABLES;" mirza_pro 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Database user fixed successfully!"
    echo "✓ Web panel should now work at: http://49.12.191.114:9731/webpanel/"
else
    echo ""
    echo "✗ Connection test failed. Please check MySQL logs."
fi
