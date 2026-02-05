-- Migration: create users and logs tables for bursary app
-- Run this on the `bursarystore` database (phpMyAdmin or mysql CLI)

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'storekeeper',
  `full_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` TEXT,
  `mac` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Example: do NOT store plaintext passwords. Generate a bcrypt hash with PHP before inserting.
-- Example PHP snippet to create an admin user (run via CLI PHP):
-- <?php
-- require 'assets/inc/config.php';
-- $username = 'admin';
-- $password = password_hash('admin123', PASSWORD_DEFAULT);
-- $role = 'admin';
-- $full_name = 'System Administrator';
-- $stmt = $mysqli->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
-- $stmt->bind_param('ssss', $username, $password, $role, $full_name);
-- $stmt->execute();
-- echo "Inserted user id: {$stmt->insert_id}\n";
-- ?>
