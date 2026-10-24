-- Maut Ka Khel (Semester Routine & Deadline Tracker)
-- Database Schema & Initial Seed Data

CREATE DATABASE IF NOT EXISTS `maut_ka_khel` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `maut_ka_khel`;

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(50) NOT NULL,
    `dim_color` VARCHAR(50) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tasks Table
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_uid` VARCHAR(100) NULL,
    `course_code` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `deadline_date` DATE NOT NULL,
    `is_done` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`course_code`),
    INDEX (`deadline_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin: username: admin / password: admin123
INSERT IGNORE INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$wO7vK9M9a4v7O0C4F6zY0eeD9hXwzZ0eJ9K3F5hF7sK1m1l8W5K0y');

-- Default Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('eyebrow', 'Summer term · Aug – Sep 2026'),
('hero_title', 'Everything due,<br>in <em>one line</em>.'),
('hero_subtitle', 'Semester routine & deadline ledger tracker'),
('allow_public_toggle', '1');

-- Default Courses
INSERT IGNORE INTO `courses` (`id`, `code`, `name`, `color`, `dim_color`, `sort_order`) VALUES
(1, 'pe', 'Professional English', '#d8735a', '#3a2620', 1),
(2, 'mllab', 'ML lab', '#e3a13d', '#3a2c14', 2),
(3, 'dc', 'Data communication', '#3fa796', '#173430', 3),
(4, 'dclab', 'Data communication lab', '#63c2b4', '#173430', 4),
(5, 'idp', 'IDP II', '#8f7fe8', '#241f3d', 5),
(6, 'ml', 'ML', '#d8578f', '#341c29', 6);
