-- Create Mirza Pro database and user (separate from VPN panel)
CREATE DATABASE IF NOT EXISTS mirza_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP USER IF EXISTS 'mirza_user'@'localhost';
CREATE USER 'mirza_user'@'localhost' IDENTIFIED BY 'QGMo+/1XnWeaOrMUlwgjGjouk9YU/OW4';
GRANT ALL PRIVILEGES ON mirza_pro.* TO 'mirza_user'@'localhost';
FLUSH PRIVILEGES;

-- Show results
SHOW DATABASES LIKE 'mirza%';
SELECT User, Host, plugin FROM mysql.user WHERE User='mirza_user';
