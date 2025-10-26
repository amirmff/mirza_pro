#!/bin/bash

# Quick fix for setup wizard - creates admin table and saves DB credentials

echo "Fixing setup wizard..."

# Create admin table if it doesn't exist
echo "Creating admin table..."
mysql --defaults-file=/etc/mysql/debian.cnf mirza_pro <<'EOF'
CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` INT(11) NOT NULL AUTO_INCREMENT,
  `username_admin` VARCHAR(255) NOT NULL,
  `password_admin` VARCHAR(255) NOT NULL,
  `rule` VARCHAR(50) NOT NULL DEFAULT 'administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `username_admin` (`username_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

if [ $? -eq 0 ]; then
    echo "✓ Admin table created successfully"
else
    echo "✗ Failed to create admin table"
    exit 1
fi

# Create DB credentials JSON for setup wizard
if [ -f /root/.mirza_db_credentials ]; then
    source /root/.mirza_db_credentials
    
    cat > /var/www/mirza_pro/webpanel/.db_credentials.json <<EOF
{
    "db_host": "localhost",
    "db_name": "${DB_NAME}",
    "db_user": "${DB_USER}",
    "db_password": "${DB_PASSWORD}"
}
EOF
    
    chown www-data:www-data /var/www/mirza_pro/webpanel/.db_credentials.json
    chmod 600 /var/www/mirza_pro/webpanel/.db_credentials.json
    
    echo "✓ Database credentials saved for setup wizard"
else
    echo "⚠ Database credentials file not found"
fi

echo ""
echo "✓ Setup wizard fixed!"
echo "Now you can complete the setup at: http://YOUR_SERVER_IP:9731/webpanel/setup.php"
