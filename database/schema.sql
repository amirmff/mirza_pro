-- Mirza Pro Database Schema
-- Admin Panel Tables

CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` INT(11) NOT NULL AUTO_INCREMENT,
  `username_admin` VARCHAR(255) NOT NULL,
  `password_admin` VARCHAR(255) NOT NULL,
  `rule` VARCHAR(50) NOT NULL DEFAULT 'administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `username_admin` (`username_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session and activity logs
CREATE TABLE IF NOT EXISTS `admin_activity_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web panel settings
CREATE TABLE IF NOT EXISTS `web_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(255) NOT NULL,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
