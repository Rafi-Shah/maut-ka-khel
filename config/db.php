<?php
/**
 * Database Connection & Auto-Setup
 * Handles PDO connection and automatically initializes tables and default seeds if not present.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'maut_ka_khel');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First try to ensure database exists (works on local XAMPP / root access)
        try {
            $serverDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            $tempPdo = new PDO($serverDsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        } catch (PDOException $ignored) {
            // In shared hosting (cPanel), database is created manually in control panel
        }

        // Connect directly to the target database
        $dbDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dbDsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Auto-run migrations & seed if necessary
        initTablesAndSeeds($pdo);

        return $pdo;
    } catch (PDOException $e) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT));
    }
}

function initTablesAndSeeds(PDO $pdo): void {
    // 1. Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Courses Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `courses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `color` VARCHAR(50) NOT NULL,
        `dim_color` VARCHAR(50) NOT NULL,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Tasks Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tasks` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed Admin if not exists
    $adminCount = $pdo->query("SELECT COUNT(*) FROM `admins`")->fetchColumn();
    if ($adminCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `admins` (`username`, `password`) VALUES (?, ?)");
        $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt->execute(['admin', $defaultPassHash]);
    }

    // Seed Settings if not exists
    $settingsDefaults = [
        'eyebrow' => 'Summer term · Aug – Sep 2026',
        'hero_title' => 'Everything due,<br>in <em>one line</em>.',
        'hero_subtitle' => 'Semester routine & deadline ledger tracker',
        'allow_public_toggle' => '1'
    ];
    $checkSetting = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE `setting_key` = ?");
    $insertSetting = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
    foreach ($settingsDefaults as $key => $val) {
        $checkSetting->execute([$key]);
        if ($checkSetting->fetchColumn() == 0) {
            $insertSetting->execute([$key, $val]);
        }
    }

    // Seed Courses if empty
    $courseCount = $pdo->query("SELECT COUNT(*) FROM `courses`")->fetchColumn();
    if ($courseCount == 0) {
        $courses = [
            ['code' => 'pe',    'name' => 'Professional English',     'color' => '#d8735a', 'dim' => '#3a2620', 'order' => 1],
            ['code' => 'mllab', 'name' => 'ML lab',                    'color' => '#e3a13d', 'dim' => '#3a2c14', 'order' => 2],
            ['code' => 'dc',    'name' => 'Data communication',        'color' => '#3fa796', 'dim' => '#173430', 'order' => 3],
            ['code' => 'dclab', 'name' => 'Data communication lab',    'color' => '#63c2b4', 'dim' => '#173430', 'order' => 4],
            ['code' => 'idp',   'name' => 'IDP II',                    'color' => '#8f7fe8', 'dim' => '#241f3d', 'order' => 5],
            ['code' => 'ml',    'name' => 'ML',                        'color' => '#d8578f', 'dim' => '#341c29', 'order' => 6],
        ];
        $insertCourse = $pdo->prepare("INSERT INTO `courses` (`code`, `name`, `color`, `dim_color`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
        foreach ($courses as $c) {
            $insertCourse->execute([$c['code'], $c['name'], $c['color'], $c['dim'], $c['order']]);
        }
    }

    // Seed Tasks if empty
    $taskCount = $pdo->query("SELECT COUNT(*) FROM `tasks`")->fetchColumn();
    if ($taskCount == 0) {
        $tasks = [
            ['uid' => 'pe-speaking',                 'c' => 'pe',    't' => 'Speaking test (video submission)', 'date' => '2026-08-15'],
            ['uid' => 'pe-ct2',                      'c' => 'pe',    't' => 'CT-2',                             'date' => '2026-08-16'],
            ['uid' => 'pe-presentation',             'c' => 'pe',    't' => 'Presentation',                     'date' => '2026-08-17'],
            ['uid' => 'idp-lab34',                   'c' => 'idp',   't' => 'Lab report 3, 4',                  'date' => '2026-08-18'],
            ['uid' => 'idp-poster-submit',           'c' => 'idp',   't' => 'Poster Submit',                    'date' => '2026-08-18'],
            ['uid' => 'ml-presentation',             'c' => 'ml',    't' => 'Presentation',                     'date' => '2026-08-20'],
            ['uid' => 'dc-presentation',             'c' => 'dc',    't' => 'Presentation',                     'date' => '2026-08-21'],
            ['uid' => 'dclab-lab2',                  'c' => 'dclab', 't' => 'Lab report 2',                     'date' => '2026-08-21'],
            ['uid' => 'pe-listening',                'c' => 'pe',    't' => 'Listening test',                   'date' => '2026-08-23'],
            ['uid' => 'ml-ct2',                      'c' => 'ml',    't' => 'CT 2',                             'date' => '2026-08-23'],
            ['uid' => 'dclab-project-presentation',  'c' => 'dclab', 't' => 'Project Presentation',             'date' => '2026-08-24'],
            ['uid' => 'idp-lab5',                    'c' => 'idp',   't' => 'Lab report 5',                     'date' => '2026-08-25'],
            ['uid' => 'idp-promo',                   'c' => 'idp',   't' => 'Promo video',                      'date' => '2026-08-26'],
            ['uid' => 'idp-final-report',            'c' => 'idp',   't' => 'Final project report',             'date' => '2026-08-30'],
            ['uid' => 'dclab-lab-final',             'c' => 'dclab', 't' => 'Lab Final',                        'date' => '2026-08-31'],
            ['uid' => 'dclab-project-report',        'c' => 'dclab', 't' => 'Project report',                  'date' => '2026-09-01'],
            ['uid' => 'idp-poster-presentation',     'c' => 'idp',   't' => 'Poster Presentation',              'date' => '2026-09-01'],
            ['uid' => 'mllab-labs123',               'c' => 'mllab', 't' => 'Lab report 1, 2, 3',               'date' => '2026-09-10'],
        ];
        $insertTask = $pdo->prepare("INSERT INTO `tasks` (`task_uid`, `course_code`, `title`, `deadline_date`, `is_done`) VALUES (?, ?, ?, ?, 0)");
        foreach ($tasks as $t) {
            $insertTask->execute([$t['uid'], $t['c'], $t['t'], $t['date']]);
        }
    }
}
