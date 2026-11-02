-- PPAP Database Schema with 3 Types of Users & Activities Module
CREATE DATABASE IF NOT EXISTS `ppap_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ppap_db`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'branch_manager', 'user') NOT NULL DEFAULT 'user',
    `branch` VARCHAR(150) NOT NULL DEFAULT 'កំពង់ផែស្វយ័តភ្នំពេញ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activities Table (សកម្មភាពការងារ)
CREATE TABLE IF NOT EXISTS `activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `year` INT NOT NULL DEFAULT 2026,
    `entity` VARCHAR(150) NOT NULL,
    `department` VARCHAR(150) NOT NULL,
    `branch` VARCHAR(150) DEFAULT NULL,
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
    `is_submitted` TINYINT(1) NOT NULL DEFAULT 1,
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `approved_at` DATETIME DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `approved_by_name` VARCHAR(150) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Entities Table (អង្គភាព)
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

-- Seed 42 Official Entities (ថ្នាក់កណ្តាល និងរាជធានី-ខេត្ត)
INSERT INTO `entities` (`name_km`, `name_en`, `code`)
VALUES
    ('អគ្គនាយកដ្ឋានរដ្ឋបាល និងហិរញ្ញវត្ថុ', 'General Dept. of Admin & Finance', 'GDAF'),
    ('អគ្គនាយកដ្ឋានផែនការ និងគោលនយោបាយ', 'General Dept. of Planning & Policy', 'GDPP'),
    ('អគ្គនាយកដ្ឋានបច្ចេកទេស', 'General Dept. of Technical Affairs', 'GDTA'),
    ('អគ្គនាយកដ្ឋានសាធារណការ', 'General Dept. of Public Works', 'GDPW'),
    ('អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវគោក', 'General Dept. of Land Transport', 'GDLT'),
    ('អគ្គនាយកដ្ឋានដឹកជញ្ជូនផ្លូវទឹក ផ្លូវសមុទ្រ និងកំពង់ផែ', 'General Dept. of Waterway, Maritime Transport & Ports', 'GDWMTP'),
    ('អគ្គនាយកដ្ឋានភស្តុភារកម្ម', 'General Dept. of Logistics', 'GDL'),
    ('អគ្គនាយកដ្ឋានប្រព័ន្ធចម្រោះទឹកកខ្វក់', 'General Dept. of Wastewater Treatment Systems', 'GDWTS'),
    ('អគ្គនាយកដ្ឋានបច្ចេកវិទ្យា និងទំនាក់ទំនងសាធារណៈ', 'General Dept. of Technology & Public Relations', 'GDTPR'),
    ('វិទ្យាស្ថានតេជោសែន សាធារណការ និងដឹកជញ្ជូន', 'Techo Sen Institute of Public Works & Transport', 'TSI'),
    ('អគ្គាធិការដ្ឋាន', 'General Inspectorate', 'GI'),
    ('នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'Dept. of Internal Audit', 'DIA'),
    ('នាយកដ្ឋានផ្លូវដែក', 'Dept. of Railways', 'DR'),
    ('អង្គភាពលទ្ធកម្ម', 'Procurement Unit', 'PU'),
    ('កំពង់ផែស្វយ័តក្រុងព្រះសីហនុ', 'Sihanoukville Autonomous Port', 'PAS'),
    ('កំពង់ផែស្វយ័តភ្នំពេញ', 'Phnom Penh Autonomous Port', 'PPAP'),
    ('មន្ទីរពិសោធន៍សំណង់ និងសាធារណការ', 'Construction & Public Works Laboratory', 'CPWL'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបន្ទាយមានជ័យ', 'Dept. of Public Works & Transport (Banteay Meanchey)', 'DPWT-BMC'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបាត់ដំបង', 'Dept. of Public Works & Transport (Battambang)', 'DPWT-BTB'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ចាម', 'Dept. of Public Works & Transport (Kampong Cham)', 'DPWT-KCM'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ឆ្នាំង', 'Dept. of Public Works & Transport (Kampong Chhnang)', 'DPWT-KCH'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ស្ពឺ', 'Dept. of Public Works & Transport (Kampong Speu)', 'DPWT-KSP'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពង់ធំ', 'Dept. of Public Works & Transport (Kampong Thom)', 'DPWT-KT'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកំពត', 'Dept. of Public Works & Transport (Kampot)', 'DPWT-KP'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកណ្តាល', 'Dept. of Public Works & Transport (Kandal)', 'DPWT-KD'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកោះកុង', 'Dept. of Public Works & Transport (Koh Kong)', 'DPWT-KK'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តក្រចេះ', 'Dept. of Public Works & Transport (Kratie)', 'DPWT-KR'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនមណ្ឌលគិរី', 'Dept. of Public Works & Transport (Mondulkiri)', 'DPWT-MK'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនរាជធានីភ្នំពេញ', 'Dept. of Public Works & Transport (Phnom Penh)', 'DPWT-PP'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះវិហារ', 'Dept. of Public Works & Transport (Preah Vihear)', 'DPWT-PV'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រៃវែង', 'Dept. of Public Works & Transport (Prey Veng)', 'DPWT-PVG'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តពោធិ៍សាត់', 'Dept. of Public Works & Transport (Pursat)', 'DPWT-PST'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តរតនគិរី', 'Dept. of Public Works & Transport (Ratanakiri)', 'DPWT-RK'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តសៀមរាប', 'Dept. of Public Works & Transport (Siem Reap)', 'DPWT-SR'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តព្រះសីហនុ', 'Dept. of Public Works & Transport (Preah Sihanouk)', 'DPWT-SHV'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្ទឹងត្រែង', 'Dept. of Public Works & Transport (Stung Treng)', 'DPWT-ST'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តស្វាយរៀង', 'Dept. of Public Works & Transport (Svay Rieng)', 'DPWT-SVR'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តតាកែវ', 'Dept. of Public Works & Transport (Takeo)', 'DPWT-TK'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តកែប', 'Dept. of Public Works & Transport (Kep)', 'DPWT-KEP'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តបៃលិន', 'Dept. of Public Works & Transport (Pailin)', 'DPWT-PL'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តឧត្តរមានជ័យ', 'Dept. of Public Works & Transport (Oddar Meanchey)', 'DPWT-OMC'),
    ('មន្ទីរសាធារណការ និងដឹកជញ្ជូនខេត្តត្បូងឃ្មុំ', 'Dept. of Public Works & Transport (Tboung Khmum)', 'DPWT-TBK')
ON DUPLICATE KEY UPDATE `name_km` = VALUES(`name_km`);

-- Departments Table (នាយកដ្ឋាន)
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

-- Seed Default Departments
INSERT INTO `departments` (`entity_id`, `name_km`, `name_en`, `code`)
VALUES
    (1, 'នាយកដ្ឋានអធិការកិច្ច', 'Dept. of Inspection', 'DI'),
    (1, 'នាយកដ្ឋានសវនកម្មផ្ទៃក្នុង', 'Dept. of Internal Audit', 'DIA'),
    (2, 'នាយកដ្ឋានរដ្ឋបាល និងបុគ្គលិក', 'Dept. of Admin & Personnel', 'DAP'),
    (2, 'នាយកដ្ឋានហិរញ្ញវត្ថុ និងគណនេយ្យ', 'Dept. of Finance & Accounting', 'DFA'),
    (2, 'នាយកដ្ឋានបណ្តុះបណ្តាល', 'Dept. of Training', 'DT'),
    (3, 'នាយកដ្ឋានប្រតិបត្តិការកំពង់ផែ', 'Dept. of Port Operations', 'DPO'),
    (3, 'នាយកដ្ឋានភស្តុភារកម្ម និងឃ្លាំង', 'Dept. of Logistics & Warehouse', 'DLW'),
    (4, 'នាយកដ្ឋានផែនការ និងស្ថិតិ', 'Dept. of Planning & Statistics', 'DPS'),
    (4, 'នាយកដ្ឋានទីផ្សារ និងពាណិជ្ជកម្ម', 'Dept. of Marketing & Commerce', 'DMC'),
    (5, 'នាយកដ្ឋានព័ត៌មានវិទ្យា', 'Dept. of Information Technology', 'DIT'),
    (5, 'នាយកដ្ឋានប្រព័ន្ធស្វ័យប្រវត្តិកម្ម និងទូរគមនាគមន៍', 'Dept. of Automation & Telecom', 'DAT')
ON DUPLICATE KEY UPDATE `entity_id` = VALUES(`entity_id`), `name_en` = VALUES(`name_en`), `code` = VALUES(`code`);

-- Seed Default Accounts
INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `branch`)
VALUES
    ('Super Admin', 'superadmin', 'superadmin@ppap.com', '$2y$10$lAZKR/9JHsxpaFq7o1gm1OJqm1QBnTa6Agcmwpjjl03l2K591FN0m', 'super_admin', 'ទីស្នាក់ការកណ្តាល (Head Office)'),
    ('System Admin', 'admin', 'admin@ppap.com', '$2y$10$8nhJfPn3GkGpY7IEoEkAlegMQ0OxTFb3P6aA8qv.ZT7QcypRp./7C', 'admin', 'កំពង់ផែកុងតឺន័រ LM17 (LM17 Container Terminal)'),
    ('Branch Manager LM17', 'manager', 'manager@ppap.com', '$2y$10$T8rQy32c02m9F08.tC3zW.8xH5x8g92u3yM4wB5F7r2yN0wL1m0vW', 'branch_manager', 'កំពង់ផែកុងតឺន័រ LM17 (LM17 Container Terminal)'),
    ('Normal User', 'user', 'user@ppap.com', '$2y$10$dYNh69d79CAd2oNF.m8kV.C4Ma8Ue66VF6jMtReW2eIGtnTvvdEO6', 'user', 'កំពង់ផែពហុបំណង TS3 (TS3 Multipurpose Terminal)')
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `role` = VALUES(`role`),
    `branch` = VALUES(`branch`);

-- Activity Evaluations Table (ការវាយតម្លៃសកម្មភាពរបស់អង្គភាពសាមី)
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
