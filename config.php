<?php
/**
 * Database & Application Configuration
 * PPAP - Login & Registration System with Role-Based Access Control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Internationalization (i18n) System
require_once __DIR__ . '/includes/lang.php';
init_language();

// Database Credentials (Default XAMPP settings)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ppap_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO Database Connection
 * Automatically creates database, tables, role columns, and seeds default users!
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsnWithoutDb = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        // Connect to MySQL server
        $pdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);

        // Auto-create database if missing
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Auto-create users table with role column if missing
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `full_name` VARCHAR(100) NOT NULL,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user',
                `branch` VARCHAR(150) NOT NULL DEFAULT 'កំពង់ផែស្វយ័តភ្នំពេញ',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createTableSql);

        // Auto-create activities table if missing
        $createActivitiesSql = "
            CREATE TABLE IF NOT EXISTS `activities` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `year` INT NOT NULL DEFAULT 2026,
                `entity` VARCHAR(150) NOT NULL,
                `department` VARCHAR(150) NOT NULL,
                `description` TEXT NOT NULL,
                `target` VARCHAR(255) NOT NULL,
                `start_quarter` VARCHAR(50) NOT NULL,
                `end_quarter` VARCHAR(50) NOT NULL,
                `activity_type` VARCHAR(100) NOT NULL,
                `activity_subtype` VARCHAR(100) DEFAULT NULL,
                `priority_policy` VARCHAR(255) DEFAULT NULL,
                `priority_policy_detail` TEXT DEFAULT NULL,
                `responsible_person` VARCHAR(150) NOT NULL,
                `implementer` VARCHAR(150) NOT NULL,
                `kpi_1` VARCHAR(255) DEFAULT NULL,
                `kpi_2` VARCHAR(255) DEFAULT NULL,
                `kpi_3` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createActivitiesSql);

        // Auto-create entities table if missing
        $createEntitiesSql = "
            CREATE TABLE IF NOT EXISTS `entities` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name_km` VARCHAR(150) NOT NULL UNIQUE,
                `name_en` VARCHAR(150) DEFAULT NULL,
                `code` VARCHAR(50) DEFAULT NULL,
                `unit_level` ENUM('national', 'sub_national') NOT NULL DEFAULT 'national',
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createEntitiesSql);

        // Auto-create departments table if missing
        $createDepartmentsSql = "
            CREATE TABLE IF NOT EXISTS `departments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_id` INT DEFAULT NULL,
                `name_km` VARCHAR(150) NOT NULL UNIQUE,
                `name_en` VARCHAR(150) DEFAULT NULL,
                `code` VARCHAR(50) DEFAULT NULL,
                `unit_level` ENUM('national', 'sub_national') NOT NULL DEFAULT 'national',
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`entity_id`) REFERENCES `entities`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createDepartmentsSql);

        // Ensure `unit_level` column exists in `departments` and `entities`
        $deptLevelCheck = $pdo->query("SHOW COLUMNS FROM `departments` LIKE 'unit_level'")->fetch();
        if (!$deptLevelCheck) {
            $pdo->exec("ALTER TABLE `departments` ADD COLUMN `unit_level` ENUM('national', 'sub_national') NOT NULL DEFAULT 'national' AFTER `code`");
        }
        $entLevelCheck = $pdo->query("SHOW COLUMNS FROM `entities` LIKE 'unit_level'")->fetch();
        if (!$entLevelCheck) {
            $pdo->exec("ALTER TABLE `entities` ADD COLUMN `unit_level` ENUM('national', 'sub_national') NOT NULL DEFAULT 'national' AFTER `code`");
        }

        // Ensure `role` column exists and supports `branch_manager`
        $roleColCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
        if (!$roleColCheck) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('super_admin', 'admin', 'branch_manager', 'user') NOT NULL DEFAULT 'user' AFTER `password`");
        } else {
            if (isset($roleColCheck['Type']) && strpos($roleColCheck['Type'], 'branch_manager') === false) {
                $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('super_admin', 'admin', 'branch_manager', 'user') NOT NULL DEFAULT 'user'");
            }
        }

        // Ensure `branch` column exists for existing tables and has adequate width
        $branchColCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'branch'")->fetch();
        if (!$branchColCheck) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `branch` VARCHAR(150) NOT NULL DEFAULT 'កំពង់ផែស្វយ័តភ្នំពេញ' AFTER `role`");
        } else {
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `branch` VARCHAR(150) NOT NULL DEFAULT 'កំពង់ផែស្វយ័តភ្នំពេញ'");
        }

        // Ensure `branch` column exists in activities
        $actBranchCheck = $pdo->query("SHOW COLUMNS FROM `activities` LIKE 'branch'")->fetch();
        if (!$actBranchCheck) {
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `branch` VARCHAR(150) DEFAULT NULL AFTER `department`");
        } else {
            $pdo->exec("ALTER TABLE `activities` MODIFY COLUMN `branch` VARCHAR(150) DEFAULT NULL");
        }

        // Ensure `activity_code` column exists in activities
        $actCodeCheck = $pdo->query("SHOW COLUMNS FROM `activities` LIKE 'activity_code'")->fetch();
        if (!$actCodeCheck) {
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `activity_code` VARCHAR(50) DEFAULT NULL AFTER `id`");
        }

        // Ensure submission & approval tracking columns exist in activities
        $actSubmitCheck = $pdo->query("SHOW COLUMNS FROM `activities` LIKE 'is_submitted'")->fetch();
        if (!$actSubmitCheck) {
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `is_submitted` TINYINT(1) NOT NULL DEFAULT 1 AFTER `implementer`");
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `is_submitted`");
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `submitted_at`");
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `is_approved`");
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `approved_at`");
            $pdo->exec("ALTER TABLE `activities` ADD COLUMN `approved_by_name` VARCHAR(150) DEFAULT NULL AFTER `approved_by`");
        }

        // Auto-create activity_evaluations table for quarterly evaluations on approved activities
        $createEvaluationsSql = "
            CREATE TABLE IF NOT EXISTS `activity_evaluations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `activity_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `quarter` VARCHAR(50) NOT NULL,
                `kpi_1_achieved` DECIMAL(6,2) DEFAULT NULL,
                `kpi_1_checked` TINYINT(1) DEFAULT 0,
                `kpi_2_achieved` DECIMAL(6,2) DEFAULT NULL,
                `kpi_3_achieved` DECIMAL(6,2) DEFAULT NULL,
                `kpi_3_checked` TINYINT(1) DEFAULT 0,
                `actual_result` TEXT DEFAULT NULL,
                `challenges` TEXT DEFAULT NULL,
                `attachment_path` VARCHAR(255) DEFAULT NULL,
                `attachment_name` VARCHAR(255) DEFAULT NULL,
                `ref_attachment_path` VARCHAR(255) DEFAULT NULL,
                `ref_attachment_name` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`activity_id`) REFERENCES `activities`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createEvaluationsSql);

        // Ensure ref_attachment columns exist in activity_evaluations for dynamic reference upload
        $refColCheck = $pdo->query("SHOW COLUMNS FROM `activity_evaluations` LIKE 'ref_attachment_path'")->fetch();
        if (!$refColCheck) {
            $pdo->exec("ALTER TABLE `activity_evaluations` ADD COLUMN `ref_attachment_path` VARCHAR(255) DEFAULT NULL AFTER `attachment_name`");
            $pdo->exec("ALTER TABLE `activity_evaluations` ADD COLUMN `ref_attachment_name` VARCHAR(255) DEFAULT NULL AFTER `ref_attachment_path`");
        }

        // Auto-create announcements table if missing (សេចក្តីជូនដំណឹង)
        $createAnnouncementsSql = "
            CREATE TABLE IF NOT EXISTS `announcements` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `subject` VARCHAR(255) NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT 'សេចក្តីជូនដំណឹង',
                `description` TEXT NOT NULL,
                `datetime_receive` DATETIME NOT NULL,
                `datetime_expire` DATETIME NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createAnnouncementsSql);

        // Auto-create activity_priorities table if missing (សកម្មភាពអាទិភាព)
        $createActivityPrioritiesSql = "
            CREATE TABLE IF NOT EXISTS `activity_priorities` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `activity_code` VARCHAR(50) NOT NULL,
                `activity_type` VARCHAR(150) NOT NULL,
                `activity_subtype` VARCHAR(150) DEFAULT NULL,
                `noted` TEXT DEFAULT NULL,
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_activity_code` (`activity_code`),
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createActivityPrioritiesSql);

        // Ensure activity_code is not restricted by a UNIQUE index
        try {
            $uniqueIndexes = $pdo->query("SHOW INDEX FROM `activity_priorities` WHERE Column_name = 'activity_code' AND Non_unique = 0")->fetchAll();
            foreach ($uniqueIndexes as $uidx) {
                $pdo->exec("ALTER TABLE `activity_priorities` DROP INDEX `{$uidx['Key_name']}`");
            }
        } catch (Exception $e) {}

        // Auto-seed default entities, default departments, default users & default announcements & activity priorities
        seed_default_entities($pdo);
        seed_default_departments($pdo);
        seed_default_users($pdo);
        seed_default_announcements($pdo);
        seed_default_activity_priorities($pdo);

        return $pdo;
    } catch (PDOException $e) {
        die("<div style='font-family: sans-serif; padding: 20px; background: #ffebee; color: #c62828; border-radius: 8px; max-width: 600px; margin: 40px auto; border: 1px solid #ef9a9a;'>"
            . "<h3>Database Connection Error</h3>"
            . "<p>Unable to connect to MySQL database. Please make sure MySQL is running in XAMPP.</p>"
            . "<p><small><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</small></p>"
            . "</div>");
    }
}

/**
 * PPAP Official Operational Branches & Work Units (សាខាប្រតិបត្តិការ / អង្គភាពការងារ)
 */
function get_ppap_branches() {
    return [
        // អង្គភាពថ្នាក់កណ្តាល និងកំពង់ផែ (Central Entities & Ports)
        'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ',
        'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ',
        'អគ្គនាយកដ្ឋានបច្ចេកទេស',
        'អគ្គនាយកដ្ឋានសាធារណការ',
        'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក',
        'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ',
        'អគ្គនាយកដ្ឋានភស្តុភារកម្ម',
        'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់',
        'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ',
        'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន',
        'អគ្គាធិការដ្ឋាន',
        'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង',
        'នាយកដ្ឋានផ្លូវដែក',
        'អង្គភាពលទ្ធកម្ម',
        'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ',
        'កំពង់ផែស្វយ័តភ្នំពេញ',
        'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ',
        // មន្ទីរសាធារណការ និងដឹកជញ្ជូន រាជធានី-ខេត្តទាំង២៥ (Capital & Provincial Departments)
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបន្ទាយមានជ័យ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបាត់ដំបង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ចាម',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ឆ្នាំង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ស្ពឺ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ធំ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពត',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកណ្តាល',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកោះកុង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តក្រចេះ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនមណ្ឌលគិរី',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនរាជធានីភ្នំពេញ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះវិហារ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រៃវែង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តពោធិ៍សាត់',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តរតនគិរី',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តសៀមរាប',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះសីហនុ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្ទឹងត្រែង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្វាយរៀង',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តតាកែវ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកែប',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបៃលិន',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តឧត្តរមានជ័យ',
        'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តត្បូងឃ្មុំ'
    ];
}

/**
 * Seed the 3 required types of users
 */
function seed_default_users(PDO $pdo) {
    $defaultUsers = [
        [
            'full_name' => 'Super Admin',
            'username'  => 'superadmin',
            'email'     => 'superadmin@ppap.com',
            'password'  => 'SuperAdmin123!',
            'role'      => 'super_admin',
            'branch'    => 'កំពង់ផែស្វយ័តភ្នំពេញ'
        ],
        [
            'full_name' => 'System Admin',
            'username'  => 'admin',
            'email'     => 'admin@ppap.com',
            'password'  => 'Admin123!',
            'role'      => 'admin',
            'branch'    => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ'
        ],
        [
            'full_name' => 'Branch Manager PPAP',
            'username'  => 'manager',
            'email'     => 'manager@ppap.com',
            'password'  => 'Manager123!',
            'role'      => 'branch_manager',
            'branch'    => 'កំពង់ផែស្វយ័តភ្នំពេញ'
        ],
        [
            'full_name' => 'Normal User',
            'username'  => 'user',
            'email'     => 'user@ppap.com',
            'password'  => 'User123!',
            'role'      => 'user',
            'branch'    => 'កំពង់ផែស្វយ័តភ្នំពេញ'
        ]
    ];

    $checkStmt = $pdo->prepare("SELECT id, role, password, branch FROM `users` WHERE `username` = :username OR `email` = :email LIMIT 1");
    $insertStmt = $pdo->prepare("
        INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `branch`)
        VALUES (:full_name, :username, :email, :password, :role, :branch)
    ");
    $updateStmt = $pdo->prepare("UPDATE `users` SET `role` = :role, `password` = :password WHERE `id` = :id");

    foreach ($defaultUsers as $u) {
        $checkStmt->execute([
            'username' => $u['username'],
            'email'    => $u['email']
        ]);
        $existing = $checkStmt->fetch();

        if (!$existing) {
            $insertStmt->execute([
                'full_name' => $u['full_name'],
                'username'  => $u['username'],
                'email'     => $u['email'],
                'password'  => password_hash($u['password'], PASSWORD_DEFAULT),
                'role'      => $u['role'],
                'branch'    => $u['branch']
            ]);
        } else {
            // If branch is missing/empty, update it
            if (empty($existing['branch'])) {
                $pdo->prepare("UPDATE `users` SET `branch` = :branch WHERE `id` = :id")->execute(['branch' => $u['branch'], 'id' => $existing['id']]);
            }
            // If the password hash in the DB is invalid or doesn't match, repair it
            if (!password_verify($u['password'], $existing['password']) || $existing['role'] !== $u['role']) {
                $updateStmt->execute([
                    'role'     => $u['role'],
                    'password' => password_hash($u['password'], PASSWORD_DEFAULT),
                    'id'       => $existing['id']
                ]);
            }
        }
    }
}

/**
 * Seed initial official announcement (សេចក្តីជូនដំណឹងគំរូផ្លូវការ) if table is empty
 */
function seed_default_announcements(PDO $pdo) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `announcements`")->fetchColumn();
        if ($count == 0) {
            $adminId = $pdo->query("SELECT id FROM `users` WHERE role IN ('super_admin', 'admin') ORDER BY id ASC LIMIT 1")->fetchColumn();
            $ins = $pdo->prepare("
                INSERT INTO `announcements` (`subject`, `title`, `description`, `datetime_receive`, `datetime_expire`, `is_active`, `created_by`)
                VALUES (:subject, :title, :description, :datetime_receive, :datetime_expire, :is_active, :created_by)
            ");
            $ins->execute([
                'subject'          => 'លិខិតលេខ០១៧៧៦ សក ចុះថ្ងៃទី៤ ខែមេសា ឆ្នាំ២០២៤ របស់ក្រសួងសាធារណការ និងដឹកជញ្ជូន',
                'title'            => 'សេចក្តីជូនដំណឹង',
                'description'      => "សូមគោរពជម្រាបជូន ឯកឧត្តម លោកជំទាវ លោក លោកស្រី មេត្តាជ្រាបថា៖\n\nប្រព័ន្ធវាយតម្លៃផែនការសកម្មភាពអាទិភាព បើកទទួលរបាយការណ៍វឌ្ឍនភាព នៃការអនុវត្ត ផែនការ ប្រចាំខែ កក្កដា ឆ្នាំ ២០២៦ ចាប់ពីថ្ងៃទី២៥ តទៅ។\n\nសូម ឯកឧត្តម លោកជំទាវ លោក លោកស្រី មេត្តាចាត់ចែងបញ្ចូលរបាយការណ៍ដែលទទួលបន្ទុក និងផ្ញើមកក្រុមការងារ យ៉ាងយូរបំផុត ត្រឹមថ្ងៃទី ៣១ ខែ កក្កដា ឆ្នាំ ២០២៦ ជាកំហិតដើម្បីក្រុមការងាររៀបចំទូកសរុបផ្ញើជូនរដ្ឋមន្ត្រី ទាន់ពេលវេលាកំណត់។",
                'datetime_receive' => '2026-07-25 08:00:00',
                'datetime_expire'  => '2026-07-31 23:59:59',
                'is_active'        => 1,
                'created_by'       => $adminId ?: 1
            ]);
        }
    } catch (PDOException $e) {
        // Silently ignore if table not yet created
    }
}

/**
 * Seed default entities (អង្គភាព) if table is empty
 */
function seed_default_entities(PDO $pdo) {
    try {
        $defaultEntities = [
            ['name_km' => 'អគ្គាធិការដ្ឋាន', 'name_en' => 'General Inspectorate', 'code' => 'GI', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ', 'name_en' => 'General Dept. of Admin & Finance', 'code' => 'GDAF', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានភស្តុភារកម្ម', 'name_en' => 'General Dept. of Logistics', 'code' => 'GDL', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានផែនការ', 'name_en' => 'General Dept. of Planning', 'code' => 'GDP', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា', 'name_en' => 'General Dept. of Technology & IT', 'code' => 'GDIT', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ', 'name_en' => 'General Dept. of Planning & Policy', 'code' => 'GDPP', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានសវនកម្មបច្ចេកទេស', 'name_en' => 'General Dept. of Technical Audit', 'code' => 'GDTA', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានសាធារណការ', 'name_en' => 'General Dept. of Public Works', 'code' => 'GDPW', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក', 'name_en' => 'General Dept. of Land Transport', 'code' => 'GDLT', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ', 'name_en' => 'General Dept. of Waterway, Maritime Transport & Ports', 'code' => 'GDWMTP', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់', 'name_en' => 'General Dept. of Wastewater Treatment Systems', 'code' => 'GDWTS', 'unit_level' => 'national'],
            ['name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ', 'name_en' => 'General Dept. of Technology & Public Relations', 'code' => 'GDTPR', 'unit_level' => 'national'],
            ['name_km' => 'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន', 'name_en' => 'Techo Sen Institute of Public Works & Transport', 'code' => 'TSI', 'unit_level' => 'national'],
            ['name_km' => 'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'name_en' => 'Dept. of Internal Audit', 'code' => 'DIA', 'unit_level' => 'national'],
            ['name_km' => 'នាយកដ្ឋានផ្លូវដែក', 'name_en' => 'Dept. of Railways', 'code' => 'DR', 'unit_level' => 'national'],
            ['name_km' => 'អង្គភាពលទ្ធកម្ម', 'name_en' => 'Procurement Unit', 'code' => 'PU', 'unit_level' => 'national'],
            ['name_km' => 'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ', 'name_en' => 'Sihanoukville Autonomous Port', 'code' => 'PAS', 'unit_level' => 'national'],
            ['name_km' => 'កំពង់ផែស្វយ័តភ្នំពេញ', 'name_en' => 'Phnom Penh Autonomous Port', 'code' => 'PPAP', 'unit_level' => 'national'],
            ['name_km' => 'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ', 'name_en' => 'Construction & Public Works Laboratory', 'code' => 'CPWL', 'unit_level' => 'national'],
            // ២៥ រាជធានី-ខេត្ត (Sub-national)
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបន្ទាយមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Banteay Meanchey)', 'code' => 'DPWT-BMC', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបាត់ដំបង', 'name_en' => 'Dept. of Public Works & Transport (Battambang)', 'code' => 'DPWT-BTB', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ចាម', 'name_en' => 'Dept. of Public Works & Transport (Kampong Cham)', 'code' => 'DPWT-KCM', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ឆ្នាំង', 'name_en' => 'Dept. of Public Works & Transport (Kampong Chhnang)', 'code' => 'DPWT-KCH', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ស្ពឺ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Speu)', 'code' => 'DPWT-KSP', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ធំ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Thom)', 'code' => 'DPWT-KT', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពត', 'name_en' => 'Dept. of Public Works & Transport (Kampot)', 'code' => 'DPWT-KP', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកណ្តាល', 'name_en' => 'Dept. of Public Works & Transport (Kandal)', 'code' => 'DPWT-KD', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកោះកុង', 'name_en' => 'Dept. of Public Works & Transport (Koh Kong)', 'code' => 'DPWT-KK', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តក្រចេះ', 'name_en' => 'Dept. of Public Works & Transport (Kratie)', 'code' => 'DPWT-KR', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនមណ្ឌលគិរី', 'name_en' => 'Dept. of Public Works & Transport (Mondulkiri)', 'code' => 'DPWT-MK', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនរាជធានីភ្នំពេញ', 'name_en' => 'Dept. of Public Works & Transport (Phnom Penh)', 'code' => 'DPWT-PP', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះវិហារ', 'name_en' => 'Dept. of Public Works & Transport (Preah Vihear)', 'code' => 'DPWT-PV', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រៃវែង', 'name_en' => 'Dept. of Public Works & Transport (Prey Veng)', 'code' => 'DPWT-PVG', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តពោធិ៍សាត់', 'name_en' => 'Dept. of Public Works & Transport (Pursat)', 'code' => 'DPWT-PST', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តរតនគិរី', 'name_en' => 'Dept. of Public Works & Transport (Ratanakiri)', 'code' => 'DPWT-RK', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តសៀមរាប', 'name_en' => 'Dept. of Public Works & Transport (Siem Reap)', 'code' => 'DPWT-SR', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះសីហនុ', 'name_en' => 'Dept. of Public Works & Transport (Preah Sihanouk)', 'code' => 'DPWT-SHV', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្ទឹងត្រែង', 'name_en' => 'Dept. of Public Works & Transport (Stung Treng)', 'code' => 'DPWT-ST', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្វាយរៀង', 'name_en' => 'Dept. of Public Works & Transport (Svay Rieng)', 'code' => 'DPWT-SVR', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តតាកែវ', 'name_en' => 'Dept. of Public Works & Transport (Takeo)', 'code' => 'DPWT-TK', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកែប', 'name_en' => 'Dept. of Public Works & Transport (Kep)', 'code' => 'DPWT-KEP', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបៃលិន', 'name_en' => 'Dept. of Public Works & Transport (Pailin)', 'code' => 'DPWT-PL', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តឧត្តរមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Oddar Meanchey)', 'code' => 'DPWT-OMC', 'unit_level' => 'sub_national'],
            ['name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តត្បូងឃ្មុំ', 'name_en' => 'Dept. of Public Works & Transport (Tboung Khmum)', 'code' => 'DPWT-TBK', 'unit_level' => 'sub_national']
        ];
        $stmt = $pdo->prepare("INSERT INTO `entities` (`name_km`, `name_en`, `code`, `unit_level`) VALUES (:km, :en, :code, :level) ON DUPLICATE KEY UPDATE `name_en` = VALUES(`name_en`), `code` = VALUES(`code`), `unit_level` = VALUES(`unit_level`)");
        foreach ($defaultEntities as $ent) {
            $stmt->execute(['km' => $ent['name_km'], 'en' => $ent['name_en'], 'code' => $ent['code'], 'level' => $ent['unit_level']]);
        }
    } catch (Exception $e) {
        // Silently skip if table not ready yet
    }
}

/**
 * Retrieve all registered entities (អង្គភាព)
 */
function get_all_entities(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM `entities` ORDER BY id ASC");
        $list = $stmt->fetchAll();
        if (!empty($list)) {
            return $list;
        }
    } catch (Exception $e) {
        // Fallback below
    }
    return [
        ['id' => 1, 'name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ', 'name_en' => 'General Dept. of Admin & Finance', 'code' => 'GDAF'],
        ['id' => 2, 'name_km' => 'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ', 'name_en' => 'General Dept. of Planning & Policy', 'code' => 'GDPP'],
        ['id' => 3, 'name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកទេស', 'name_en' => 'General Dept. of Technical Affairs', 'code' => 'GDTA'],
        ['id' => 4, 'name_km' => 'អគ្គនាយកដ្ឋានសាធារណការ', 'name_en' => 'General Dept. of Public Works', 'code' => 'GDPW'],
        ['id' => 5, 'name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក', 'name_en' => 'General Dept. of Land Transport', 'code' => 'GDLT'],
        ['id' => 6, 'name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ', 'name_en' => 'General Dept. of Waterway, Maritime Transport & Ports', 'code' => 'GDWMTP'],
        ['id' => 7, 'name_km' => 'អគ្គនាយកដ្ឋានភស្តុភារកម្ម', 'name_en' => 'General Dept. of Logistics', 'code' => 'GDL'],
        ['id' => 8, 'name_km' => 'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់', 'name_en' => 'General Dept. of Wastewater Treatment Systems', 'code' => 'GDWTS'],
        ['id' => 9, 'name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ', 'name_en' => 'General Dept. of Technology & Public Relations', 'code' => 'GDTPR'],
        ['id' => 10, 'name_km' => 'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន', 'name_en' => 'Techo Sen Institute of Public Works & Transport', 'code' => 'TSI'],
        ['id' => 11, 'name_km' => 'អគ្គាធិការដ្ឋាន', 'name_en' => 'General Inspectorate', 'code' => 'GI'],
        ['id' => 12, 'name_km' => 'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'name_en' => 'Dept. of Internal Audit', 'code' => 'DIA'],
        ['id' => 13, 'name_km' => 'នាយកដ្ឋានផ្លូវដែក', 'name_en' => 'Dept. of Railways', 'code' => 'DR'],
        ['id' => 14, 'name_km' => 'អង្គភាពលទ្ធកម្ម', 'name_en' => 'Procurement Unit', 'code' => 'PU'],
        ['id' => 15, 'name_km' => 'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ', 'name_en' => 'Sihanoukville Autonomous Port', 'code' => 'PAS'],
        ['id' => 16, 'name_km' => 'កំពង់ផែស្វយ័តភ្នំពេញ', 'name_en' => 'Phnom Penh Autonomous Port', 'code' => 'PPAP'],
        ['id' => 17, 'name_km' => 'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ', 'name_en' => 'Construction & Public Works Laboratory', 'code' => 'CPWL'],
        ['id' => 18, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបន្ទាយមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Banteay Meanchey)', 'code' => 'DPWT-BMC'],
        ['id' => 19, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបាត់ដំបង', 'name_en' => 'Dept. of Public Works & Transport (Battambang)', 'code' => 'DPWT-BTB'],
        ['id' => 20, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ចាម', 'name_en' => 'Dept. of Public Works & Transport (Kampong Cham)', 'code' => 'DPWT-KCM'],
        ['id' => 21, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ឆ្នាំង', 'name_en' => 'Dept. of Public Works & Transport (Kampong Chhnang)', 'code' => 'DPWT-KCH'],
        ['id' => 22, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ស្ពឺ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Speu)', 'code' => 'DPWT-KSP'],
        ['id' => 23, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ធំ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Thom)', 'code' => 'DPWT-KT'],
        ['id' => 24, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពត', 'name_en' => 'Dept. of Public Works & Transport (Kampot)', 'code' => 'DPWT-KP'],
        ['id' => 25, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកណ្តាល', 'name_en' => 'Dept. of Public Works & Transport (Kandal)', 'code' => 'DPWT-KD'],
        ['id' => 26, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកោះកុង', 'name_en' => 'Dept. of Public Works & Transport (Koh Kong)', 'code' => 'DPWT-KK'],
        ['id' => 27, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តក្រចេះ', 'name_en' => 'Dept. of Public Works & Transport (Kratie)', 'code' => 'DPWT-KR'],
        ['id' => 28, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនមណ្ឌលគិរី', 'name_en' => 'Dept. of Public Works & Transport (Mondulkiri)', 'code' => 'DPWT-MK'],
        ['id' => 29, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនរាជធានីភ្នំពេញ', 'name_en' => 'Dept. of Public Works & Transport (Phnom Penh)', 'code' => 'DPWT-PP'],
        ['id' => 30, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះវិហារ', 'name_en' => 'Dept. of Public Works & Transport (Preah Vihear)', 'code' => 'DPWT-PV'],
        ['id' => 31, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រៃវែង', 'name_en' => 'Dept. of Public Works & Transport (Prey Veng)', 'code' => 'DPWT-PVG'],
        ['id' => 32, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តពោធិ៍សាត់', 'name_en' => 'Dept. of Public Works & Transport (Pursat)', 'code' => 'DPWT-PST'],
        ['id' => 33, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តរតនគិរី', 'name_en' => 'Dept. of Public Works & Transport (Ratanakiri)', 'code' => 'DPWT-RK'],
        ['id' => 34, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តសៀមរាប', 'name_en' => 'Dept. of Public Works & Transport (Siem Reap)', 'code' => 'DPWT-SR'],
        ['id' => 35, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះសីហនុ', 'name_en' => 'Dept. of Public Works & Transport (Preah Sihanouk)', 'code' => 'DPWT-SHV'],
        ['id' => 36, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្ទឹងត្រែង', 'name_en' => 'Dept. of Public Works & Transport (Stung Treng)', 'code' => 'DPWT-ST'],
        ['id' => 37, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្វាយរៀង', 'name_en' => 'Dept. of Public Works & Transport (Svay Rieng)', 'code' => 'DPWT-SVR'],
        ['id' => 38, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តតាកែវ', 'name_en' => 'Dept. of Public Works & Transport (Takeo)', 'code' => 'DPWT-TK'],
        ['id' => 39, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកែប', 'name_en' => 'Dept. of Public Works & Transport (Kep)', 'code' => 'DPWT-KEP'],
        ['id' => 40, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបៃលិន', 'name_en' => 'Dept. of Public Works & Transport (Pailin)', 'code' => 'DPWT-PL'],
        ['id' => 41, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តឧត្តរមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Oddar Meanchey)', 'code' => 'DPWT-OMC'],
        ['id' => 42, 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តត្បូងឃ្មុំ', 'name_en' => 'Dept. of Public Works & Transport (Tboung Khmum)', 'code' => 'DPWT-TBK']
    ];
}

/**
 * Seed default departments (នាយកដ្ឋាន) and link them to their respective parent entities
 */
function seed_default_departments(PDO $pdo) {
    try {
        // Map each parent entity to its official subordinate departments
        $entityDeptMap = [
            'អគ្គាធិការដ្ឋាន' => [
                ['name_km' => 'នាយកដ្ឋានអធិការកិច្ច', 'name_en' => 'Dept. of Inspection', 'code' => 'DI'],
                ['name_km' => 'នាយកដ្ឋានតាមដាន និងវាយតម្លៃ', 'name_en' => 'Dept. of Monitoring & Evaluation', 'code' => 'DME']
            ],
            'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ' => [
                ['name_km' => 'នាយកដ្ឋានរដ្ឋបាល និងបុគ្គលិក', 'name_en' => 'Dept. of Admin & Personnel', 'code' => 'DAP'],
                ['name_km' => 'នាយកដ្ឋានហិរញ្ញវត្ថុ និងគណនេយ្យ', 'name_en' => 'Dept. of Finance & Accounting', 'code' => 'DFA'],
                ['name_km' => 'នាយកដ្ឋានបណ្តុះបណ្តាល', 'name_en' => 'Dept. of Training', 'code' => 'DT']
            ],
            'អគ្គនាយកដ្ឋានភស្តុភារកម្ម' => [
                ['name_km' => 'នាយកដ្ឋានប្រតិបត្តិការកំពង់ផែ', 'name_en' => 'Dept. of Port Operations', 'code' => 'DPO'],
                ['name_km' => 'នាយកដ្ឋានភស្តុភារកម្ម និងឃ្លាំង', 'name_en' => 'Dept. of Logistics & Warehouse', 'code' => 'DLW']
            ],
            'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ' => [
                ['name_km' => 'នាយកដ្ឋានផែនការ និងស្ថិតិ', 'name_en' => 'Dept. of Planning & Statistics', 'code' => 'DPS'],
                ['name_km' => 'នាយកដ្ឋានគោលនយោបាយ និងយុទ្ធសាស្ត្រ', 'name_en' => 'Dept. of Policy & Strategy', 'code' => 'DPSt'],
                ['name_km' => 'នាយកដ្ឋានទីផ្សារ និងពាណិជ្ជកម្ម', 'name_en' => 'Dept. of Marketing & Commerce', 'code' => 'DMC']
            ],
            'អគ្គនាយកដ្ឋានផែនការ' => [
                ['name_km' => 'នាយកដ្ឋានផែនការ និងស្ថិតិ', 'name_en' => 'Dept. of Planning & Statistics', 'code' => 'DPS'],
                ['name_km' => 'នាយកដ្ឋានទីផ្សារ និងពាណិជ្ជកម្ម', 'name_en' => 'Dept. of Marketing & Commerce', 'code' => 'DMC']
            ],
            'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ' => [
                ['name_km' => 'នាយកដ្ឋានព័ត៌មានវិទ្យា', 'name_en' => 'Dept. of Information Technology', 'code' => 'DIT'],
                ['name_km' => 'នាយកដ្ឋានទំនាក់ទំនងសាធារណៈ', 'name_en' => 'Dept. of Public Relations', 'code' => 'DPR']
            ],
            'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា' => [
                ['name_km' => 'នាយកដ្ឋានព័ត៌មានវិទ្យា', 'name_en' => 'Dept. of Information Technology', 'code' => 'DIT'],
                ['name_km' => 'នាយកដ្ឋានប្រព័ន្ធស្វ័យប្រវត្តិកម្ម និងទូរគមនាគមន៍', 'name_en' => 'Dept. of Automation & Telecom', 'code' => 'DAT']
            ],
            'អគ្គនាយកដ្ឋានបច្ចេកទេស' => [
                ['name_km' => 'នាយកដ្ឋានស្រាវជ្រាវបច្ចេកទេស', 'name_en' => 'Dept. of Technical Research', 'code' => 'DTR'],
                ['name_km' => 'នាយកដ្ឋានស្តង់ដារបច្ចេកទេស', 'name_en' => 'Dept. of Technical Standards', 'code' => 'DTS']
            ],
            'អគ្គនាយកដ្ឋានសាធារណការ' => [
                ['name_km' => 'នាយកដ្ឋានផ្លូវថ្នល់', 'name_en' => 'Dept. of Roads', 'code' => 'DRo'],
                ['name_km' => 'នាយកដ្ឋានស្ពាន និងលូ', 'name_en' => 'Dept. of Bridges & Drainage', 'code' => 'DBD']
            ],
            'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក' => [
                ['name_km' => 'នាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក', 'name_en' => 'Dept. of Land Transport', 'code' => 'DLT'],
                ['name_km' => 'នាយកដ្ឋានសុវត្ថិភាពចរាចរណ៍ផ្លូវគោក', 'name_en' => 'Dept. of Road Safety', 'code' => 'DRS']
            ],
            'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ' => [
                ['name_km' => 'នាយកដ្ឋានផ្លូវទឹក', 'name_en' => 'Dept. of Waterways', 'code' => 'DW'],
                ['name_km' => 'នាយកដ្ឋានកំពង់ផែ និងសមុទ្រ', 'name_en' => 'Dept. of Ports & Maritime', 'code' => 'DPM']
            ],
            'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់' => [
                ['name_km' => 'នាយកដ្ឋានគ្រប់គ្រងប្រព័ន្ធទឹកកខ្វក់', 'name_en' => 'Dept. of Wastewater Management', 'code' => 'DWM']
            ],
            'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន' => [
                ['name_km' => 'នាយកដ្ឋានបណ្តុះបណ្តាល និងស្រាវជ្រាវ', 'name_en' => 'Dept. of Training & Research', 'code' => 'DTR']
            ],
            'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង' => [
                ['name_km' => 'ការិយាល័យសវនកម្មហិរញ្ញវត្ថុ', 'name_en' => 'Office of Financial Audit', 'code' => 'OFA'],
                ['name_km' => 'ការិយាល័យសវនកម្មប្រតិបត្តិការ', 'name_en' => 'Office of Operational Audit', 'code' => 'OOA']
            ],
            'នាយកដ្ឋានផ្លូវដែក' => [
                ['name_km' => 'ការិយាល័យគ្រប់គ្រងប្រតិបត្តិការផ្លូវដែក', 'name_en' => 'Office of Railway Operations', 'code' => 'ORO']
            ],
            'អង្គភាពលទ្ធកម្ម' => [
                ['name_km' => 'ការិយាល័យលទ្ធកម្មសាធារណៈ', 'name_en' => 'Office of Public Procurement', 'code' => 'OPP']
            ],
            'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ' => [
                ['name_km' => 'នាយកដ្ឋានប្រតិបត្តិការកំពង់ផែព្រះសីហនុ', 'name_en' => 'Dept. of Sihanoukville Port Operations', 'code' => 'DSPO']
            ],
            'កំពង់ផែស្វយ័តភ្នំពេញ' => [
                ['name_km' => 'នាយកដ្ឋានប្រតិបត្តិការកុងតឺន័រ', 'name_en' => 'Dept. of Container Operations', 'code' => 'DCO'],
                ['name_km' => 'នាយកដ្ឋានរដ្ឋបាល និងធនធានមនុស្ស', 'name_en' => 'Dept. of Admin & HR', 'code' => 'DAHR'],
                ['name_km' => 'នាយកដ្ឋានផែនការ និងទីផ្សារ', 'name_en' => 'Dept. of Planning & Marketing', 'code' => 'DPMk']
            ],
            'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ' => [
                ['name_km' => 'ការិយាល័យពិសោធន៍សម្ភារៈសំណង់', 'name_en' => 'Office of Construction Materials Testing', 'code' => 'OCMT']
            ]
        ];

        // Retrieve existing registered entities
        $entRows = $pdo->query("SELECT id, name_km FROM `entities`")->fetchAll(PDO::FETCH_ASSOC);
        $entMap = [];
        foreach ($entRows as $er) {
            $entMap[$er['name_km']] = (int)$er['id'];
        }

        $checkStmt  = $pdo->prepare("SELECT id, entity_id FROM `departments` WHERE `name_km` = :km LIMIT 1");
        $updateStmt = $pdo->prepare("UPDATE `departments` SET `entity_id` = :ent_id, `name_en` = COALESCE(`name_en`, :en), `code` = COALESCE(`code`, :code) WHERE `id` = :id");
        $insertStmt = $pdo->prepare("INSERT INTO `departments` (`name_km`, `name_en`, `code`, `entity_id`) VALUES (:km, :en, :code, :ent_id)");

        foreach ($entityDeptMap as $entName => $depts) {
            $entId = $entMap[$entName] ?? null;
            if (!$entId) continue;

            foreach ($depts as $d) {
                $checkStmt->execute(['km' => $d['name_km']]);
                $existing = $checkStmt->fetch();
                if ($existing) {
                    // Automatically link existing departments that have missing or mismatched entity_id
                    if (empty($existing['entity_id']) || (int)$existing['entity_id'] !== $entId) {
                        $updateStmt->execute([
                            'ent_id' => $entId,
                            'en'     => $d['name_en'],
                            'code'   => $d['code'],
                            'id'     => $existing['id']
                        ]);
                    }
                } else {
                    $insertStmt->execute([
                        'km'     => $d['name_km'],
                        'en'     => $d['name_en'],
                        'code'   => $d['code'],
                        'ent_id' => $entId
                    ]);
                }
            }
        }

        // Seed/Sync Official 19 National-level and 25 Sub-national units in departments table
        seed_official_units_in_departments($pdo);
    } catch (Exception $e) {
        // Silently skip if database not ready
    }
}

/**
 * Seed & Sync Official 19 National and 25 Sub-national units into departments table
 */
function seed_official_units_in_departments(PDO $pdo) {
    try {
        $natUnits = [
            ['code' => 'GI',     'name_km' => 'អគ្គាធិការដ្ឋាន', 'name_en' => 'General Inspectorate'],
            ['code' => 'GDAF',   'name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ', 'name_en' => 'General Dept. of Admin & Finance'],
            ['code' => 'GDL',    'name_km' => 'អគ្គនាយកដ្ឋានភស្តុភារកម្ម', 'name_en' => 'General Dept. of Logistics'],
            ['code' => 'GDP',    'name_km' => 'អគ្គនាយកដ្ឋានផែនការ', 'name_en' => 'General Dept. of Planning'],
            ['code' => 'GDIT',   'name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា', 'name_en' => 'General Dept. of Technology & IT'],
            ['code' => 'GDPP',   'name_km' => 'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ', 'name_en' => 'General Dept. of Planning & Policy'],
            ['code' => 'GDTA',   'name_km' => 'អគ្គនាយកដ្ឋានសវនកម្មបច្ចេកទេស', 'name_en' => 'General Dept. of Technical Audit'],
            ['code' => 'GDPW',   'name_km' => 'អគ្គនាយកដ្ឋានសាធារណការ', 'name_en' => 'General Dept. of Public Works'],
            ['code' => 'GDLT',   'name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក', 'name_en' => 'General Dept. of Land Transport'],
            ['code' => 'GDWMTP', 'name_km' => 'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ', 'name_en' => 'General Dept. of Waterway, Maritime Transport & Ports'],
            ['code' => 'GDWTS',  'name_km' => 'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់', 'name_en' => 'General Dept. of Wastewater Treatment Systems'],
            ['code' => 'GDTPR',  'name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ', 'name_en' => 'General Dept. of Technology & Public Relations'],
            ['code' => 'TSI',    'name_km' => 'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន', 'name_en' => 'Techo Sen Institute of Public Works & Transport'],
            ['code' => 'DIA',    'name_km' => 'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'name_en' => 'Dept. of Internal Audit'],
            ['code' => 'DR',     'name_km' => 'នាយកដ្ឋានផ្លូវដែក', 'name_en' => 'Dept. of Railways'],
            ['code' => 'PU',     'name_km' => 'អង្គភាពលទ្ធកម្ម', 'name_en' => 'Procurement Unit'],
            ['code' => 'PAS',    'name_km' => 'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ', 'name_en' => 'Sihanoukville Autonomous Port'],
            ['code' => 'PPAP',   'name_km' => 'កំពង់ផែស្វយ័តភ្នំពេញ', 'name_en' => 'Phnom Penh Autonomous Port'],
            ['code' => 'CPWL',   'name_km' => 'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ', 'name_en' => 'Construction & Public Works Laboratory']
        ];

        $subUnits = [
            ['code' => 'DPWT-BMC', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបន្ទាយមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Banteay Meanchey)'],
            ['code' => 'DPWT-BTB', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបាត់ដំបង', 'name_en' => 'Dept. of Public Works & Transport (Battambang)'],
            ['code' => 'DPWT-KCM', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ចាម', 'name_en' => 'Dept. of Public Works & Transport (Kampong Cham)'],
            ['code' => 'DPWT-KCH', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ឆ្នាំង', 'name_en' => 'Dept. of Public Works & Transport (Kampong Chhnang)'],
            ['code' => 'DPWT-KSP', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ស្ពឺ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Speu)'],
            ['code' => 'DPWT-KT',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ធំ', 'name_en' => 'Dept. of Public Works & Transport (Kampong Thom)'],
            ['code' => 'DPWT-KP',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពត', 'name_en' => 'Dept. of Public Works & Transport (Kampot)'],
            ['code' => 'DPWT-KD',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកណ្តាល', 'name_en' => 'Dept. of Public Works & Transport (Kandal)'],
            ['code' => 'DPWT-KK',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកោះកុង', 'name_en' => 'Dept. of Public Works & Transport (Koh Kong)'],
            ['code' => 'DPWT-KR',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តក្រចេះ', 'name_en' => 'Dept. of Public Works & Transport (Kratie)'],
            ['code' => 'DPWT-MK',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនមណ្ឌលគិរី', 'name_en' => 'Dept. of Public Works & Transport (Mondulkiri)'],
            ['code' => 'DPWT-PP',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនរាជធានីភ្នំពេញ', 'name_en' => 'Dept. of Public Works & Transport (Phnom Penh)'],
            ['code' => 'DPWT-PV',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះវិហារ', 'name_en' => 'Dept. of Public Works & Transport (Preah Vihear)'],
            ['code' => 'DPWT-PVG', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រៃវែង', 'name_en' => 'Dept. of Public Works & Transport (Prey Veng)'],
            ['code' => 'DPWT-PST', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តពោធិ៍សាត់', 'name_en' => 'Dept. of Public Works & Transport (Pursat)'],
            ['code' => 'DPWT-RK',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តរតនគិរី', 'name_en' => 'Dept. of Public Works & Transport (Ratanakiri)'],
            ['code' => 'DPWT-SR',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តសៀមរាប', 'name_en' => 'Dept. of Public Works & Transport (Siem Reap)'],
            ['code' => 'DPWT-SHV', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះសីហនុ', 'name_en' => 'Dept. of Public Works & Transport (Preah Sihanouk)'],
            ['code' => 'DPWT-ST',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្ទឹងត្រែង', 'name_en' => 'Dept. of Public Works & Transport (Stung Treng)'],
            ['code' => 'DPWT-SVR', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្វាយរៀង', 'name_en' => 'Dept. of Public Works & Transport (Svay Rieng)'],
            ['code' => 'DPWT-TK',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តតាកែវ', 'name_en' => 'Dept. of Public Works & Transport (Takeo)'],
            ['code' => 'DPWT-KEP', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកែប', 'name_en' => 'Dept. of Public Works & Transport (Kep)'],
            ['code' => 'DPWT-PL',  'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបៃលិន', 'name_en' => 'Dept. of Public Works & Transport (Pailin)'],
            ['code' => 'DPWT-OMC', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តឧត្តរមានជ័យ', 'name_en' => 'Dept. of Public Works & Transport (Oddar Meanchey)'],
            ['code' => 'DPWT-TBK', 'name_km' => 'មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តត្បូងឃ្មុំ', 'name_en' => 'Dept. of Public Works & Transport (Tboung Khmum)']
        ];

        $entRows = $pdo->query("SELECT id, name_km, code FROM `entities`")->fetchAll(PDO::FETCH_ASSOC);
        $entByCode = [];
        $entByName = [];
        foreach ($entRows as $er) {
            if (!empty($er['code'])) $entByCode[$er['code']] = (int)$er['id'];
            $entByName[$er['name_km']] = (int)$er['id'];
        }

        $checkStmt  = $pdo->prepare("SELECT id, code, entity_id FROM `departments` WHERE `code` = :code OR `name_km` = :km LIMIT 1");
        $updateStmt = $pdo->prepare("UPDATE `departments` SET `name_km` = :km, `name_en` = COALESCE(:en, `name_en`), `code` = :code, `unit_level` = :level, `entity_id` = COALESCE(:ent_id, `entity_id`) WHERE `id` = :id");
        $insertStmt = $pdo->prepare("INSERT INTO `departments` (`name_km`, `name_en`, `code`, `unit_level`, `entity_id`) VALUES (:km, :en, :code, :level, :ent_id)");

        foreach ($natUnits as $nu) {
            $checkStmt->execute(['code' => $nu['code'], 'km' => $nu['name_km']]);
            $existing = $checkStmt->fetch();
            $entId = $entByCode[$nu['code']] ?? ($entByName[$nu['name_km']] ?? null);
            if ($existing) {
                $updateStmt->execute(['km' => $nu['name_km'], 'en' => $nu['name_en'], 'code' => $nu['code'], 'level' => 'national', 'ent_id' => $entId, 'id' => $existing['id']]);
            } else {
                $insertStmt->execute(['km' => $nu['name_km'], 'en' => $nu['name_en'], 'code' => $nu['code'], 'level' => 'national', 'ent_id' => $entId]);
            }
        }

        foreach ($subUnits as $snu) {
            $checkStmt->execute(['code' => $snu['code'], 'km' => $snu['name_km']]);
            $existing = $checkStmt->fetch();
            $entId = $entByCode[$snu['code']] ?? ($entByName[$snu['name_km']] ?? null);
            if ($existing) {
                $updateStmt->execute(['km' => $snu['name_km'], 'en' => $snu['name_en'], 'code' => $snu['code'], 'level' => 'sub_national', 'ent_id' => $entId, 'id' => $existing['id']]);
            } else {
                $insertStmt->execute(['km' => $snu['name_km'], 'en' => $snu['name_en'], 'code' => $snu['code'], 'level' => 'sub_national', 'ent_id' => $entId]);
            }
        }
    } catch (Exception $e) {
        // Silently skip if database not ready
    }
}

/**
 * Retrieve all registered departments (នាយកដ្ឋាន) with parent entity details
 */
function get_all_departments(PDO $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT d.*, e.name_km as entity_name_km, e.name_en as entity_name_en, e.code as entity_code 
            FROM `departments` d 
            LEFT JOIN `entities` e ON d.entity_id = e.id 
            ORDER BY d.id ASC
        ");
        $list = $stmt->fetchAll();
        if (!empty($list)) {
            return $list;
        }
    } catch (Exception $e) {
        // Fallback below
    }
    return [
        ['id' => 1, 'entity_id' => 1, 'name_km' => 'នាយកដ្ឋានអធិការកិច្ច', 'name_en' => 'Dept. of Inspection', 'code' => 'DI', 'entity_name_km' => 'អគ្គាធិការដ្ឋាន'],
        ['id' => 2, 'entity_id' => 1, 'name_km' => 'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'name_en' => 'Dept. of Internal Audit', 'code' => 'DIA', 'entity_name_km' => 'អគ្គាធិការដ្ឋាន'],
        ['id' => 3, 'entity_id' => 2, 'name_km' => 'នាយកដ្ឋានរដ្ឋបាល និងបុគ្គលិក', 'name_en' => 'Dept. of Admin & Personnel', 'code' => 'DAP', 'entity_name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ'],
        ['id' => 4, 'entity_id' => 2, 'name_km' => 'នាយកដ្ឋានហិរញ្ញវត្ថុ និងគណនេយ្យ', 'name_en' => 'Dept. of Finance & Accounting', 'code' => 'DFA', 'entity_name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ'],
        ['id' => 5, 'entity_id' => 2, 'name_km' => 'នាយកដ្ឋានបណ្តុះបណ្តាល', 'name_en' => 'Dept. of Training', 'code' => 'DT', 'entity_name_km' => 'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ'],
        ['id' => 6, 'entity_id' => 3, 'name_km' => 'នាយកដ្ឋានប្រតិបត្តិការកំពង់ផែ', 'name_en' => 'Dept. of Port Operations', 'code' => 'DPO', 'entity_name_km' => 'អគ្គនាយកដ្ឋានភស្តុភារកម្ម'],
        ['id' => 7, 'entity_id' => 3, 'name_km' => 'នាយកដ្ឋានភស្តុភារកម្ម និងឃ្លាំង', 'name_en' => 'Dept. of Logistics & Warehouse', 'code' => 'DLW', 'entity_name_km' => 'អគ្គនាយកដ្ឋានភស្តុភារកម្ម'],
        ['id' => 8, 'entity_id' => 4, 'name_km' => 'នាយកដ្ឋានផែនការ និងស្ថិតិ', 'name_en' => 'Dept. of Planning & Statistics', 'code' => 'DPS', 'entity_name_km' => 'អគ្គនាយកដ្ឋានផែនការ'],
        ['id' => 9, 'entity_id' => 4, 'name_km' => 'នាយកដ្ឋានទីផ្សារ និងពាណិជ្ជកម្ម', 'name_en' => 'Dept. of Marketing & Commerce', 'code' => 'DMC', 'entity_name_km' => 'អគ្គនាយកដ្ឋានផែនការ'],
        ['id' => 10, 'entity_id' => 5, 'name_km' => 'នាយកដ្ឋានព័ត៌មានវិទ្យា', 'name_en' => 'Dept. of Information Technology', 'code' => 'DIT', 'entity_name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា'],
        ['id' => 11, 'entity_id' => 5, 'name_km' => 'នាយកដ្ឋានប្រព័ន្ធស្វ័យប្រវត្តិកម្ម និងទូរគមនាគមន៍', 'name_en' => 'Dept. of Automation & Telecom', 'code' => 'DAT', 'entity_name_km' => 'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា']
    ];
}

/**
 * Role Helpers
 */
function get_role_label($role) {
    switch ($role) {
        case 'super_admin':
            return 'Super Admin';
        case 'admin':
            return 'Admin';
        case 'branch_manager':
            return is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager';
        case 'user':
        default:
            return is_khmer() ? 'មន្ត្រីទូទៅ' : 'User';
    }
}

function get_role_badge_html($role) {
    $label = htmlspecialchars(get_role_label($role));
    $cssClass = 'badge-role badge-' . htmlspecialchars(str_replace('_', '-', $role));
    
    $icon = '';
    if ($role === 'super_admin') {
        // Crown / Shield icon
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 1l2.928 6.36 6.944.576-5.26 4.62 1.584 6.784L10 15.82 3.804 19.34l1.584-6.784-5.26-4.62 6.944-.576L10 1z" clip-rule="evenodd" /></svg>';
    } elseif ($role === 'admin') {
        // Shield / Key icon
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 2a1 1 0 00-1 1v1a1 1 0 002 0V3a1 1 0 00-1-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>';
    } elseif ($role === 'branch_manager') {
        // Building / Port terminal icon
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" /></svg>';
    } else {
        // User icon
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>';
    }

    return "<span class=\"{$cssClass}\">{$icon} {$label}</span>";
}

function is_super_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function is_admin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'], true);
}

function is_branch_manager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'branch_manager';
}

/**
 * Get specific Dashboard URL based on Role
 */
function get_dashboard_url($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? 'user';
    }
    switch ($role) {
        case 'super_admin':
            return 'Dashboard_Super_Admin.php';
        case 'admin':
            return 'Dashboard_admin.php';
        case 'branch_manager':
            return 'Dashboard_Branch_Manager.php';
        case 'user':
        default:
            return 'Dashboard_Normal_User.php';
    }
}

/**
 * Role-Based Access Guards
 */
function require_super_admin() {
    require_login();
    if (!is_super_admin()) {
        set_flash('danger', 'Access denied. Super Admin privileges required.');
        header('Location: ' . get_dashboard_url());
        exit;
    }
}

function require_admin_access() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . get_dashboard_url());
        exit;
    }
}

function require_branch_manager_access() {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', ['branch_manager', 'admin', 'super_admin'], true)) {
        set_flash('danger', 'Access denied. Branch Manager privileges required.');
        header('Location: ' . get_dashboard_url());
        exit;
    }
}

/**
 * CSRF Protection Helpers
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash Message Helpers
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Auth Helper: Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Auth Helper: Require login, otherwise redirect
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('danger', 'Please log in to access this page.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate official Activity Identifier Code (e.g. GDAF-022, PPAP-005)
 */
function get_activity_code(array $activity, ?PDO $pdo = null): string {
    static $codeCache = [];
    $entityName = trim($activity['entity'] ?? '');
    $actId = (int)($activity['id'] ?? 0);

    if (!isset($codeCache[$entityName]) && $pdo) {
        try {
            $st = $pdo->prepare("SELECT code FROM `entities` WHERE `name_km` = :km LIMIT 1");
            $st->execute(['km' => $entityName]);
            $row = $st->fetch();
            if ($row && !empty($row['code'])) {
                $codeCache[$entityName] = trim($row['code']);
            }
        } catch (Exception $e) {}
    }

    if (!isset($codeCache[$entityName])) {
        $fallbacks = [
            'អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ' => 'GDAF',
            'អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ' => 'GDPP',
            'អគ្គនាយកដ្ឋានបច្ចេកទេស' => 'GDTA',
            'អគ្គនាយកដ្ឋានសាធារណការ' => 'GDPW',
            'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក' => 'GDLT',
            'អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ' => 'GDWMTP',
            'អគ្គនាយកដ្ឋានភស្តុភារកម្ម' => 'GDL',
            'អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់' => 'GDWTS',
            'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ' => 'GDTPR',
            'អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងព័ត៌មានវិទ្យា' => 'GDTI',
            'វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន' => 'TSI',
            'អគ្គាធិការដ្ឋាន' => 'GI',
            'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង' => 'DIA',
            'នាយកដ្ឋានផ្លូវដែក' => 'DR',
            'អង្គភាពលទ្ធកម្ម' => 'PU',
            'កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ' => 'PAS',
            'កំពង់ផែស្វយ័តភ្នំពេញ' => 'PPAP',
            'មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ' => 'CPWL'
        ];
        $codeCache[$entityName] = $fallbacks[$entityName] ?? 'ACT';
    }

    $prefix = $codeCache[$entityName] ?? 'ACT';
    return $prefix . '-' . str_pad((string)$actId, 3, '0', STR_PAD_LEFT);
}

/**
 * Helper to parse quarter string into integer 1, 2, 3, 4
 */
function parse_quarter_num(?string $qStr): int {
    if (empty($qStr)) return 4;
    if (strpos($qStr, '១') !== false || strpos($qStr, '1') !== false || stripos($qStr, 'q1') !== false) return 1;
    if (strpos($qStr, '២') !== false || strpos($qStr, '2') !== false || stripos($qStr, 'q2') !== false) return 2;
    if (strpos($qStr, '៣') !== false || strpos($qStr, '3') !== false || stripos($qStr, 'q3') !== false) return 3;
    if (strpos($qStr, '៤') !== false || strpos($qStr, '4') !== false || stripos($qStr, 'q4') !== false) return 4;
    return 4;
}

/**
 * Helper to calculate the score of an individual activity evaluation record:
 * Formula: (kpi_1_achieved=10%) + (kpi_2_achieved * 80%) + (kpi_3_achieved=10%) = 100%
 */
function calculate_activity_kpi_score(?array $evalRow): ?float {
    if (!$evalRow) return null;
    $k1 = (float)($evalRow['kpi_1_achieved'] ?? 0);
    $k2 = (float)($evalRow['kpi_2_achieved'] ?? 0);
    $k3 = (float)($evalRow['kpi_3_achieved'] ?? 0);
    $score = $k1 + ($k2 * 0.8) + $k3;
    return min(100.0, max(0.0, round($score, 2)));
}

/**
 * Fetch all evaluations recorded for a specific activity
 */
function get_activity_evaluations(PDO $pdo, int $activityId): array {
    try {
        $st = $pdo->prepare('
            SELECT e.*, u.full_name as evaluator_name, u.username as evaluator_username 
            FROM activity_evaluations e 
            LEFT JOIN users u ON e.user_id = u.id 
            WHERE e.activity_id = :aid 
            ORDER BY e.id ASC
        ');
        $st->execute(['aid' => $activityId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Centralized POST handler for Activity Evaluations
 */
function process_evaluation_request(PDO $pdo, array $currentUser, string $dashFile): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['save_evaluation', 'delete_evaluation'])) return;

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('danger', is_khmer() ? 'សុពលភាពសុវត្ថិភាពមិនត្រឹមត្រូវ (Invalid CSRF Token)' : 'Invalid security token.');
        header('Location: ' . $dashFile . '?tab=evaluation');
        exit;
    }

    $userId = (int)($currentUser['id'] ?? 0);
    $userRole = $currentUser['role'] ?? 'user';
    $userBranch = $currentUser['branch'] ?? '';

    if ($action === 'save_evaluation') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $evalId = (int)($_POST['evaluation_id'] ?? 0);
        $quarter = trim($_POST['quarter'] ?? '');
        $kpi_1_checked = !empty($_POST['kpi_1_checked']) ? 1 : 0;
        
        // Requirement 1: If users select checkbox kpi_1_checked, set fix value = 10
        if ($kpi_1_checked) {
            $kpi_1_achieved = 10.0;
        } else {
            $kpi_1_achieved = (isset($_POST['kpi_1_achieved']) && $_POST['kpi_1_achieved'] !== '') ? floatval($_POST['kpi_1_achieved']) : null;
        }

        $kpi_2_achieved = (isset($_POST['kpi_2_achieved']) && $_POST['kpi_2_achieved'] !== '') ? floatval($_POST['kpi_2_achieved']) : null;
        $kpi_3_checked = !empty($_POST['kpi_3_checked']) ? 1 : 0;
        
        // Requirement: If users select checkbox kpi_3_checked, set fix value = 10
        if ($kpi_3_checked) {
            $kpi_3_achieved = 10.0;
        } else {
            $kpi_3_achieved = (isset($_POST['kpi_3_achieved']) && $_POST['kpi_3_achieved'] !== '') ? floatval($_POST['kpi_3_achieved']) : null;
        }
        $actualResult = trim($_POST['actual_result'] ?? '');
        $challenges = trim($_POST['challenges'] ?? '');

        // Fetch activity & check is_approved
        $st = $pdo->prepare('SELECT a.*, u.branch as user_branch FROM activities a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = :id LIMIT 1');
        $st->execute(['id' => $actId]);
        $act = $st->fetch();

        if (!$act) {
            set_flash('danger', is_khmer() ? 'រកមិនឃើញសកម្មភាពនេះទេ!' : 'Activity not found.');
            header('Location: ' . $dashFile . '?tab=evaluation');
            exit;
        }

        // STRICT REQUIREMENT: Only approved activities can be evaluated!
        if (empty($act['is_approved'])) {
            set_flash('danger', is_khmer() ? 'មិនអាចវាយតម្លៃបានទេ! សកម្មភាពនេះមិនទាន់ត្រូវបានអនុម័ត (Approved) នៅឡើយ។' : 'Cannot evaluate! This activity has not been approved yet.');
            header('Location: ' . $dashFile . '?tab=evaluation');
            exit;
        }

        // Permission check
        $actBranch = !empty($act['branch']) ? $act['branch'] : ($act['user_branch'] ?? '');
        if ($userRole === 'user' && $act['user_id'] != $userId) {
            set_flash('danger', is_khmer() ? 'លោកអ្នកអាចវាយតម្លៃបានតែសកម្មភាពផ្ទាល់ខ្លួនប៉ុណ្ណោះ!' : 'You can only evaluate your own activities.');
            header('Location: ' . $dashFile . '?tab=evaluation');
            exit;
        } elseif ($userRole === 'branch_manager' && !empty($userBranch) && $actBranch !== $userBranch && $act['user_id'] != $userId) {
            set_flash('danger', is_khmer() ? 'លោកអ្នកអាចវាយតម្លៃបានតែសកម្មភាពក្រោមសាខារបស់ខ្លួនប៉ុណ្ណោះ!' : 'You can only evaluate activities of your own branch.');
            header('Location: ' . $dashFile . '?tab=evaluation');
            exit;
        }

        if (empty($quarter)) {
            set_flash('danger', is_khmer() ? 'សូមជ្រើសរើសត្រីមាសសម្រាប់វាយតម្លៃ!' : 'Please select a quarter.');
            header('Location: ' . $dashFile . '?tab=evaluation&activity_id=' . $actId);
            exit;
        }

        $uploadDir = __DIR__ . '/uploads/evaluations/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];

        // 1. Primary quarterly attachment handling
        $attachmentPath = null;
        $attachmentName = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $origName = basename($_FILES['attachment']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts)) {
                $safeName = 'eval_' . $actId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $safeName)) {
                    $attachmentPath = 'uploads/evaluations/' . $safeName;
                    $attachmentName = $origName;
                }
            } else {
                set_flash('danger', is_khmer() ? 'ប្រភេទឯកសារមិនត្រូវបានអនុញ្ញាត (អនុញ្ញាតតែ PDF, Word, Excel, PowerPoint, រូបភាព ឬ ZIP)' : 'File type not allowed.');
                header('Location: ' . $dashFile . '?tab=evaluation&activity_id=' . $actId);
                exit;
            }
        }

        // 2. Requirement 2: Additional Reference attachment handling (when kpi_3_checked is selected)
        $refAttachmentPath = null;
        $refAttachmentName = null;
        if (!empty($_FILES['ref_attachment']['name']) && $_FILES['ref_attachment']['error'] === UPLOAD_ERR_OK) {
            $origRefName = basename($_FILES['ref_attachment']['name']);
            $refExt = strtolower(pathinfo($origRefName, PATHINFO_EXTENSION));
            if (in_array($refExt, $allowedExts)) {
                $safeRefName = 'ref_' . $actId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $refExt;
                if (move_uploaded_file($_FILES['ref_attachment']['tmp_name'], $uploadDir . $safeRefName)) {
                    $refAttachmentPath = 'uploads/evaluations/' . $safeRefName;
                    $refAttachmentName = $origRefName;
                }
            }
        }

        if ($evalId > 0) {
            // Update existing evaluation - preserve existing attachments if not re-uploaded
            $curSt = $pdo->prepare('SELECT attachment_path, attachment_name, ref_attachment_path, ref_attachment_name FROM activity_evaluations WHERE id = :id LIMIT 1');
            $curSt->execute(['id' => $evalId]);
            $cur = $curSt->fetch();

            $finalAttPath = $attachmentPath ?: ($cur['attachment_path'] ?? null);
            $finalAttName = $attachmentPath ? $attachmentName : ($cur['attachment_name'] ?? null);
            $finalRefPath = $refAttachmentPath ?: ($cur['ref_attachment_path'] ?? null);
            $finalRefName = $refAttachmentPath ? $refAttachmentName : ($cur['ref_attachment_name'] ?? null);

            $upd = $pdo->prepare('
                UPDATE activity_evaluations SET
                    quarter = :q, kpi_1_achieved = :k1, kpi_1_checked = :k1c,
                    kpi_2_achieved = :k2, kpi_3_achieved = :k3, kpi_3_checked = :k3c,
                    actual_result = :act_res, challenges = :ch,
                    attachment_path = :att_p, attachment_name = :att_n,
                    ref_attachment_path = :ref_p, ref_attachment_name = :ref_n
                WHERE id = :id AND activity_id = :aid
            ');
            $upd->execute([
                'q'       => $quarter,
                'k1'      => $kpi_1_achieved,
                'k1c'     => $kpi_1_checked,
                'k2'      => $kpi_2_achieved,
                'k3'      => $kpi_3_achieved,
                'k3c'     => $kpi_3_checked,
                'act_res' => $actualResult,
                'ch'      => $challenges,
                'att_p'   => $finalAttPath,
                'att_n'   => $finalAttName,
                'ref_p'   => $finalRefPath,
                'ref_n'   => $finalRefName,
                'id'      => $evalId,
                'aid'     => $actId
            ]);
            set_flash('success', is_khmer() ? 'ការវាយតម្លៃត្រូវបានកែប្រែដោយជោគជ័យ! ✅' : 'Evaluation updated successfully! ✅');
        } else {
            // Check if quarter already exists for this activity
            $checkQ = $pdo->prepare('SELECT id, attachment_path, attachment_name, ref_attachment_path, ref_attachment_name FROM activity_evaluations WHERE activity_id = :aid AND quarter = :q LIMIT 1');
            $checkQ->execute(['aid' => $actId, 'q' => $quarter]);
            $existing = $checkQ->fetch();

            if ($existing) {
                $finalAttPath = $attachmentPath ?: ($existing['attachment_path'] ?? null);
                $finalAttName = $attachmentPath ? $attachmentName : ($existing['attachment_name'] ?? null);
                $finalRefPath = $refAttachmentPath ?: ($existing['ref_attachment_path'] ?? null);
                $finalRefName = $refAttachmentPath ? $refAttachmentName : ($existing['ref_attachment_name'] ?? null);

                $upd = $pdo->prepare('
                    UPDATE activity_evaluations SET
                        kpi_1_achieved = :k1, kpi_1_checked = :k1c,
                        kpi_2_achieved = :k2, kpi_3_achieved = :k3, kpi_3_checked = :k3c,
                        actual_result = :act_res, challenges = :ch,
                        attachment_path = :att_p, attachment_name = :att_n,
                        ref_attachment_path = :ref_p, ref_attachment_name = :ref_n,
                        user_id = :uid
                    WHERE id = :id
                ');
                $upd->execute([
                    'k1'      => $kpi_1_achieved,
                    'k1c'     => $kpi_1_checked,
                    'k2'      => $kpi_2_achieved,
                    'k3'      => $kpi_3_achieved,
                    'k3c'     => $kpi_3_checked,
                    'act_res' => $actualResult,
                    'ch'      => $challenges,
                    'att_p'   => $finalAttPath,
                    'att_n'   => $finalAttName,
                    'ref_p'   => $finalRefPath,
                    'ref_n'   => $finalRefName,
                    'uid'     => $userId,
                    'id'      => $existing['id']
                ]);
                set_flash('success', is_khmer() ? 'ការវាយតម្លៃសម្រាប់ ' . $quarter . ' ត្រូវបានធ្វើបច្ចុប្បន្នភាពរួចរាល់! ✅' : 'Evaluation updated for ' . $quarter . '! ✅');
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO activity_evaluations (
                        activity_id, user_id, quarter,
                        kpi_1_achieved, kpi_1_checked, kpi_2_achieved,
                        kpi_3_achieved, kpi_3_checked,
                        actual_result, challenges,
                        attachment_path, attachment_name,
                        ref_attachment_path, ref_attachment_name
                    ) VALUES (
                        :aid, :uid, :q,
                        :k1, :k1c, :k2,
                        :k3, :k3c,
                        :act_res, :ch,
                        :att_p, :att_n,
                        :ref_p, :ref_n
                    )
                ');
                $ins->execute([
                    'aid'     => $actId,
                    'uid'     => $userId,
                    'q'       => $quarter,
                    'k1'      => $kpi_1_achieved,
                    'k1c'     => $kpi_1_checked,
                    'k2'      => $kpi_2_achieved,
                    'k3'      => $kpi_3_achieved,
                    'k3c'     => $kpi_3_checked,
                    'act_res' => $actualResult,
                    'ch'      => $challenges,
                    'att_p'   => $attachmentPath,
                    'att_n'   => $attachmentName,
                    'ref_p'   => $refAttachmentPath,
                    'ref_n'   => $refAttachmentName
                ]);
                set_flash('success', is_khmer() ? 'ការវាយតម្លៃរបស់អង្គភាពសាមី ត្រូវបានរក្សាទុកដោយជោគជ័យ! 🎉' : 'Evaluation saved successfully! 🎉');
            }
        }

        header('Location: ' . $dashFile . '?tab=evaluation&activity_id=' . $actId);
        exit;
    } elseif ($action === 'delete_evaluation') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        set_flash('danger', is_khmer() ? 'ទិន្នន័យវាយតម្លៃនេះមិនមានសិទ្ធិលុបចេញបានទេ (អ្នកមានសិទ្ធិកែសម្រួលប៉ុណ្ណោះ)!' : 'This evaluation cannot be deleted (Edit permission only).');
        header('Location: ' . $dashFile . '?tab=evaluation&activity_id=' . $actId);
        exit;
    }
}

/**
 * Auto-seed initial default activity priorities (CPAP1 - CPAP5)
 */
function seed_default_activity_priorities(PDO $pdo) {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `activity_priorities`")->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO `activity_priorities` (`activity_code`, `activity_type`, `activity_subtype`, `noted`, `created_by`)
                VALUES (:code, :type, :subtype, :noted, 1)
            ");
            $defaults = [
                ['code' => 'CPAP1', 'type' => 'សកម្មភាពស្នូល (Core Activity)', 'subtype' => 'ការអភិវឌ្ឍហេដ្ឋារចនាសម្ព័ន្ធ', 'noted' => 'ចង្កោមសកម្មភាពអាទិភាពទី១ នៃផែនការសកម្មភាពអាទិភាព'],
                ['code' => 'CPAP2', 'type' => 'សកម្មភាពស្នូល (Core Activity)', 'subtype' => 'ការដឹកជញ្ជូន និងភស្តុភារកម្ម', 'noted' => 'ចង្កោមសកម្មភាពអាទិភាពទី២ នៃផែនការសកម្មភាពអាទិភាព'],
                ['code' => 'CPAP3', 'type' => 'សកម្មភាពគាំទ្រ (Support Activity)', 'subtype' => 'រដ្ឋបាល និងធនធានមនុស្ស', 'noted' => 'ចង្កោមសកម្មភាពអាទិភាពទី៣ នៃផែនការសកម្មភាពអាទិភាព'],
                ['code' => 'CPAP4', 'type' => 'សកម្មភាពគាំទ្រ (Support Activity)', 'subtype' => 'ហិរញ្ញវត្ថុ និងថវិកា', 'noted' => 'ចង្កោមសកម្មភាពអាទិភាពទី៤ នៃផែនការសកម្មភាពអាទិភាព'],
                ['code' => 'CPAP5', 'type' => 'សកម្មភាពអភិវឌ្ឍន៍ (Development Activity)', 'subtype' => 'បច្ចេកវិទ្យា និងទំនើបកម្ម', 'noted' => 'ចង្កោមសកម្មភាពអាទិភាពទី៥ នៃផែនការសកម្មភាពអាទិភាព'],
            ];
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
    } catch (Exception $e) {
        // Silently skip if table not ready
    }
}

/**
 * Fetch all registered activity priorities
 */
function get_all_activity_priorities(PDO $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT p.*, u.full_name as author_name 
            FROM `activity_priorities` p 
            LEFT JOIN `users` u ON p.created_by = u.id 
            ORDER BY p.id ASC
        ");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Fetch a single activity priority by ID
 */
function get_activity_priority_by_id(PDO $pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `activity_priorities` WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Fetch distinct activity types and their associated subtypes from activity_priorities table
 * Returns an associative array: ['Type A' => ['Subtype 1', 'Subtype 2', ...], ...]
 */
function get_activity_types_and_subtypes(PDO $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT activity_type, activity_subtype 
            FROM `activity_priorities` 
            WHERE activity_type IS NOT NULL AND TRIM(activity_type) != '' 
            ORDER BY activity_type ASC, activity_subtype ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $typesMap = [];
        foreach ($rows as $r) {
            $t = trim($r['activity_type']);
            $st = trim($r['activity_subtype'] ?? '');
            if (!isset($typesMap[$t])) {
                $typesMap[$t] = [];
            }
            if ($st !== '') {
                $parts = array_map('trim', explode(',', $st));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $typesMap[$t], true)) {
                        $typesMap[$t][] = $p;
                    }
                }
                if (!in_array($st, $typesMap[$t], true)) {
                    $typesMap[$t][] = $st;
                }
            }
        }
        return $typesMap;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Resolve an entity from a user profile branch string
 * Maps profile branch (e.g. 'វិទ្យាស្ថានតេជោសែន...', 'ទីស្នាក់ការកណ្តាល (Head Office)', 'LM17...')
 * to the corresponding record in table `entities`
 */
function resolve_branch_entity(PDO $pdo, string $branch) {
    $branch = trim($branch);
    if ($branch === '') {
        return null;
    }

    static $cache = [];
    if (isset($cache[$branch])) {
        return $cache[$branch];
    }

    $entities = get_all_entities($pdo);

    // 1. Exact match on name_km, name_en, or code
    foreach ($entities as $e) {
        if ($e['name_km'] === $branch || (!empty($e['name_en']) && $e['name_en'] === $branch) || (!empty($e['code']) && $e['code'] === $branch)) {
            return $cache[$branch] = $e;
        }
    }

    // 2. Whitespace-stripped match (Khmer compound words tolerance with/without space)
    $strippedBranch = preg_replace('/\s+/', '', $branch);
    foreach ($entities as $e) {
        $strippedEntity = preg_replace('/\s+/', '', $e['name_km']);
        if ($strippedBranch === $strippedEntity) {
            return $cache[$branch] = $e;
        }
    }

    // 3. Clean branch of English text in parentheses e.g. "ទីស្នាក់ការកណ្តាល (Head Office)" -> "ទីស្នាក់ការកណ្តាល"
    $cleanBranch = trim(preg_replace('/\s*\(.*?\)/', '', $branch));
    $cleanStripped = preg_replace('/\s+/', '', $cleanBranch);
    if ($cleanBranch !== '') {
        foreach ($entities as $e) {
            $eStripped = preg_replace('/\s+/', '', $e['name_km']);
            if ($cleanBranch === $e['name_km'] || $cleanStripped === $eStripped) {
                return $cache[$branch] = $e;
            }
        }
        foreach ($entities as $e) {
            $eStripped = preg_replace('/\s+/', '', $e['name_km']);
            if (mb_strpos($cleanStripped, $eStripped) !== false || mb_strpos($eStripped, $cleanStripped) !== false) {
                return $cache[$branch] = $e;
            }
        }
    }

    // 4. PPAP / Port aliases (LM17, ទីស្នាក់ការកណ្តាល, កំពង់ផែស្វយ័តភ្នំពេញ, PPAP)
    if (mb_strpos($branch, 'LM17') !== false || mb_strpos($branch, 'ទីស្នាក់ការកណ្តាល') !== false || mb_strpos($branch, 'ភ្នំពេញ') !== false || mb_strpos($branch, 'PPAP') !== false) {
        foreach ($entities as $e) {
            if (($e['code'] ?? '') === 'PPAP' || mb_strpos($e['name_km'], 'កំពង់ផែស្វយ័តភ្នំពេញ') !== false) {
                return $cache[$branch] = $e;
            }
        }
    }

    return $cache[$branch] = null;
}

/**
 * Fetch all departments belonging to a specific entity ID
 */
function get_departments_by_entity_id(PDO $pdo, int $entityId) {
    if ($entityId <= 0) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE entity_id = :eid ORDER BY id ASC");
        $stmt->execute(['eid' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Automatically ensure DB, table, and default users exist on startup
get_db_connection();
