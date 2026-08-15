-- =====================================================================
--  GreenAcres Farm Management System
--  Database Schema + Seed Data
--  Target: MySQL / MariaDB (XAMPP)
--
--  Import via phpMyAdmin, or run the bundled web installer (install.php).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `farm_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `farm_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `inventory_items`;
DROP TABLE IF EXISTS `inventory_categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `harvests`;
DROP TABLE IF EXISTS `crops`;
DROP TABLE IF EXISTS `fields`;
DROP TABLE IF EXISTS `production_records`;
DROP TABLE IF EXISTS `health_records`;
DROP TABLE IF EXISTS `livestock`;
DROP TABLE IF EXISTS `livestock_categories`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  USERS  — system accounts with role based access control
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `full_name`     VARCHAR(120)  NOT NULL,
  `username`      VARCHAR(60)   NOT NULL UNIQUE,
  `email`         VARCHAR(140)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('admin','manager','worker') NOT NULL DEFAULT 'worker',
  `phone`         VARCHAR(30)   DEFAULT NULL,
  `avatar`        VARCHAR(180)  DEFAULT NULL,
  `status`        ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `last_login`    DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SETTINGS — single row key/value store for farm profile
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  EMPLOYEES — farm workforce records
-- ---------------------------------------------------------------------
CREATE TABLE `employees` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT DEFAULT NULL,
  `full_name`   VARCHAR(120) NOT NULL,
  `job_title`   VARCHAR(80)  NOT NULL,
  `department`  ENUM('livestock','crops','general','administration','maintenance') NOT NULL DEFAULT 'general',
  `phone`       VARCHAR(30)  DEFAULT NULL,
  `email`       VARCHAR(140) DEFAULT NULL,
  `address`     VARCHAR(200) DEFAULT NULL,
  `salary`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `hire_date`   DATE NOT NULL,
  `status`      ENUM('active','on_leave','terminated') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_emp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_emp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  LIVESTOCK
-- ---------------------------------------------------------------------
CREATE TABLE `livestock_categories` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(60) NOT NULL UNIQUE,
  `icon`        VARCHAR(40) NOT NULL DEFAULT 'cow',
  `description` VARCHAR(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `livestock` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `tag_number`       VARCHAR(40) NOT NULL UNIQUE,
  `category_id`      INT NOT NULL,
  `name`             VARCHAR(80)  DEFAULT NULL,
  `breed`            VARCHAR(80)  DEFAULT NULL,
  `gender`           ENUM('male','female') NOT NULL DEFAULT 'female',
  `date_of_birth`    DATE DEFAULT NULL,
  `weight_kg`        DECIMAL(8,2) NOT NULL DEFAULT 0,
  `status`           ENUM('healthy','sick','quarantine','pregnant','sold','deceased') NOT NULL DEFAULT 'healthy',
  `acquisition_date` DATE DEFAULT NULL,
  `acquisition_cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notes`            TEXT DEFAULT NULL,
  `created_by`       INT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ls_cat`  FOREIGN KEY (`category_id`) REFERENCES `livestock_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ls_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_ls_status` (`status`),
  INDEX `idx_ls_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `health_records` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `livestock_id`   INT NOT NULL,
  `record_type`    ENUM('vaccination','treatment','checkup','deworming','surgery') NOT NULL DEFAULT 'checkup',
  `description`    VARCHAR(255) NOT NULL,
  `medication`     VARCHAR(120) DEFAULT NULL,
  `vet_name`       VARCHAR(120) DEFAULT NULL,
  `cost`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `treatment_date` DATE NOT NULL,
  `next_due_date`  DATE DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_hr_ls` FOREIGN KEY (`livestock_id`) REFERENCES `livestock`(`id`) ON DELETE CASCADE,
  INDEX `idx_hr_due` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_records` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `category_id`  INT NOT NULL,
  `product`      VARCHAR(60) NOT NULL,
  `quantity`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unit`         VARCHAR(20) NOT NULL DEFAULT 'litres',
  `record_date`  DATE NOT NULL,
  `notes`        VARCHAR(200) DEFAULT NULL,
  `recorded_by`  INT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pr_cat`  FOREIGN KEY (`category_id`) REFERENCES `livestock_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_pr_date` (`record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CROPS
-- ---------------------------------------------------------------------
CREATE TABLE `fields` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(80) NOT NULL,
  `size_acres`  DECIMAL(10,2) NOT NULL DEFAULT 0,
  `soil_type`   ENUM('loamy','clay','sandy','silt','peaty','chalky') NOT NULL DEFAULT 'loamy',
  `location`    VARCHAR(160) DEFAULT NULL,
  `status`      ENUM('available','cultivated','fallow','preparation') NOT NULL DEFAULT 'available',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crops` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `crop_name`        VARCHAR(80) NOT NULL,
  `variety`          VARCHAR(80) DEFAULT NULL,
  `field_id`         INT NOT NULL,
  `area_planted`     DECIMAL(10,2) NOT NULL DEFAULT 0,
  `planting_date`    DATE NOT NULL,
  `expected_harvest` DATE DEFAULT NULL,
  `expected_yield`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status`           ENUM('planted','growing','flowering','ready','harvested','failed') NOT NULL DEFAULT 'planted',
  `input_cost`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notes`            TEXT DEFAULT NULL,
  `created_by`       INT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_crop_field` FOREIGN KEY (`field_id`) REFERENCES `fields`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crop_user`  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_crop_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `harvests` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `crop_id`       INT NOT NULL,
  `harvest_date`  DATE NOT NULL,
  `quantity`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unit`          VARCHAR(20) NOT NULL DEFAULT 'kg',
  `quality_grade` ENUM('A','B','C') NOT NULL DEFAULT 'A',
  `revenue`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notes`         VARCHAR(200) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_hv_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops`(`id`) ON DELETE CASCADE,
  INDEX `idx_hv_date` (`harvest_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  INVENTORY & SUPPLIERS
-- ---------------------------------------------------------------------
CREATE TABLE `suppliers` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(120) NOT NULL,
  `contact_person` VARCHAR(120) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `email`          VARCHAR(140) DEFAULT NULL,
  `address`        VARCHAR(200) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_categories` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(60) NOT NULL UNIQUE,
  `icon` VARCHAR(40) NOT NULL DEFAULT 'box'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_items` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `item_name`     VARCHAR(120) NOT NULL,
  `sku`           VARCHAR(40) NOT NULL UNIQUE,
  `category_id`   INT NOT NULL,
  `supplier_id`   INT DEFAULT NULL,
  `quantity`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unit`          VARCHAR(20) NOT NULL DEFAULT 'unit',
  `reorder_level` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unit_cost`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `location`      VARCHAR(120) DEFAULT NULL,
  `expiry_date`   DATE DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inv_cat` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_sup` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_movements` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `item_id`    INT NOT NULL,
  `type`       ENUM('in','out','adjustment') NOT NULL,
  `quantity`   DECIMAL(12,2) NOT NULL,
  `reference`  VARCHAR(80) DEFAULT NULL,
  `note`       VARCHAR(200) DEFAULT NULL,
  `user_id`    INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mv_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  FINANCE
-- ---------------------------------------------------------------------
CREATE TABLE `transactions` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `type`             ENUM('income','expense') NOT NULL,
  `category`         VARCHAR(60) NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL,
  `description`      VARCHAR(220) DEFAULT NULL,
  `reference_no`     VARCHAR(60) DEFAULT NULL,
  `payment_method`   ENUM('cash','bank','mobile_money','cheque','credit') NOT NULL DEFAULT 'cash',
  `transaction_date` DATE NOT NULL,
  `recorded_by`      INT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_tx_date` (`transaction_date`),
  INDEX `idx_tx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  TASKS
-- ---------------------------------------------------------------------
CREATE TABLE `tasks` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `title`        VARCHAR(160) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  `category`     ENUM('livestock','crops','maintenance','general','harvest','irrigation') NOT NULL DEFAULT 'general',
  `assigned_to`  INT DEFAULT NULL,
  `priority`     ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`       ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date`     DATE DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_by`   INT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_task_emp`  FOREIGN KEY (`assigned_to`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_task_status` (`status`),
  INDEX `idx_task_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ACTIVITY LOG — audit trail
-- ---------------------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT DEFAULT NULL,
  `module`      VARCHAR(40) NOT NULL,
  `action`      VARCHAR(40) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SEED DATA
--  All demo passwords are:  password123
-- =====================================================================

INSERT INTO `users` (`id`,`full_name`,`username`,`email`,`password_hash`,`role`,`phone`,`status`,`created_at`) VALUES
(1,'Kwame Mensah','admin','admin@greenacres.test','$2y$12$I4c33jmNg3DzbQsseWklYeovPjquXAcQsUoZgE/h9GEAmrmpBt9WK','admin','+233 24 111 2233','active','2024-01-15 08:00:00'),
(2,'Ama Boateng','manager','manager@greenacres.test','$2y$12$I4c33jmNg3DzbQsseWklYeovPjquXAcQsUoZgE/h9GEAmrmpBt9WK','manager','+233 20 445 8890','active','2024-02-02 09:12:00'),
(3,'Yaw Owusu','worker','worker@greenacres.test','$2y$12$I4c33jmNg3DzbQsseWklYeovPjquXAcQsUoZgE/h9GEAmrmpBt9WK','worker','+233 27 778 1204','active','2024-03-19 10:30:00');

INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('farm_name','GreenAcres Farms'),
('farm_owner','Kwame Mensah'),
('farm_location','Ejisu, Ashanti Region, Ghana'),
('farm_email','info@greenacres.test'),
('farm_phone','+233 24 111 2233'),
('currency_symbol','GHS'),
('currency_code','GHS'),
('low_stock_alerts','1'),
('date_format','d M Y'),
('established','2016');

INSERT INTO `employees` (`id`,`user_id`,`full_name`,`job_title`,`department`,`phone`,`email`,`address`,`salary`,`hire_date`,`status`) VALUES
(1,1,'Kwame Mensah','Farm Owner','administration','+233 24 111 2233','admin@greenacres.test','Ejisu, Ashanti',6500.00,'2016-03-01','active'),
(2,2,'Ama Boateng','Farm Manager','administration','+233 20 445 8890','manager@greenacres.test','Kumasi, Ashanti',4200.00,'2019-06-15','active'),
(3,3,'Yaw Owusu','Livestock Handler','livestock','+233 27 778 1204','yaw@greenacres.test','Ejisu, Ashanti',1800.00,'2021-01-11','active'),
(4,NULL,'Akosua Danso','Crop Supervisor','crops','+233 24 902 3311','akosua@greenacres.test','Juaben, Ashanti',2400.00,'2020-09-01','active'),
(5,NULL,'Kofi Asante','Field Worker','crops','+233 55 220 7781','kofi@greenacres.test','Ejisu, Ashanti',1500.00,'2022-04-18','active'),
(6,NULL,'Abena Nyarko','Veterinary Assistant','livestock','+233 26 331 9902','abena@greenacres.test','Kumasi, Ashanti',2100.00,'2022-11-07','active'),
(7,NULL,'Ibrahim Sulley','Maintenance Technician','maintenance','+233 24 556 3320','ibrahim@greenacres.test','Ejisu, Ashanti',1900.00,'2023-02-20','active'),
(8,NULL,'Esi Appiah','Poultry Attendant','livestock','+233 20 118 4477','esi@greenacres.test','Bomfa, Ashanti',1450.00,'2023-08-05','on_leave');

INSERT INTO `livestock_categories` (`id`,`name`,`icon`,`description`) VALUES
(1,'Cattle','cow','Dairy and beef cattle herd'),
(2,'Goats','goat','Local and Boer cross goats'),
(3,'Sheep','sheep','Djallonke sheep flock'),
(4,'Poultry','poultry','Layers and broilers'),
(5,'Pigs','pig','Large White cross pigs');

INSERT INTO `livestock` (`tag_number`,`category_id`,`name`,`breed`,`gender`,`date_of_birth`,`weight_kg`,`status`,`acquisition_date`,`acquisition_cost`,`notes`,`created_by`) VALUES
('CT-001',1,'Adwoa','Friesian Cross','female','2021-04-12',430.50,'healthy','2021-08-01',7800.00,'Top milk producer in the herd.',1),
('CT-002',1,'Nana','Sanga','male','2020-11-03',520.00,'healthy','2021-08-01',9200.00,'Breeding bull.',1),
('CT-003',1,'Serwaa','Friesian Cross','female','2022-02-20',380.00,'pregnant','2022-06-14',7100.00,'Expected to calve soon.',1),
('CT-004',1,'Akua','N Dama','female','2022-09-08',350.75,'healthy','2023-01-20',6600.00,NULL,2),
('CT-005',1,'Kwesi','Sanga','male','2023-05-30',290.00,'healthy','2023-09-02',5400.00,'Growing steer.',2),
('CT-006',1,'Afia','Friesian Cross','female','2021-07-19',410.00,'sick','2021-11-05',7500.00,'Under treatment for mastitis.',2),
('GT-001',2,'Kofi','Boer Cross','male','2023-01-14',48.20,'healthy','2023-04-10',950.00,NULL,2),
('GT-002',2,'Abena','West African Dwarf','female','2022-08-22',36.50,'healthy','2022-12-01',780.00,NULL,2),
('GT-003',2,'Yaa','Boer Cross','female','2023-03-05',41.00,'pregnant','2023-07-19',890.00,NULL,3),
('GT-004',2,'Kojo','West African Dwarf','male','2023-10-11',28.40,'healthy','2024-01-08',620.00,NULL,3),
('GT-005',2,'Ama','Boer Cross','female','2022-05-30',44.80,'healthy','2022-09-12',870.00,NULL,3),
('SH-001',3,'Bella','Djallonke','female','2022-06-17',32.00,'healthy','2022-10-02',700.00,NULL,2),
('SH-002',3,'Sika','Djallonke','female','2023-02-09',29.50,'healthy','2023-05-21',680.00,NULL,2),
('SH-003',3,'Tuo','Djallonke','male','2023-07-25',35.20,'quarantine','2023-11-14',720.00,'New arrival, 14 day observation.',3),
('PL-001',4,'Layer Batch A','ISA Brown','female','2024-01-10',1.90,'healthy','2024-01-10',4200.00,'250 birds in batch.',2),
('PL-002',4,'Layer Batch B','Lohmann Brown','female','2024-06-02',1.85,'healthy','2024-06-02',3900.00,'220 birds in batch.',2),
('PL-003',4,'Broiler Batch C','Cobb 500','male','2025-02-14',2.40,'healthy','2025-02-14',3100.00,'300 birds, ready in 6 weeks.',3),
('PG-001',5,'Sow One','Large White','female','2022-12-01',180.00,'healthy','2023-03-15',2400.00,NULL,2),
('PG-002',5,'Sow Two','Landrace Cross','female','2023-04-18',165.50,'pregnant','2023-08-01',2250.00,'Second farrowing expected.',2),
('PG-003',5,'Boar One','Large White','male','2022-10-09',210.00,'healthy','2023-02-11',2800.00,'Breeding boar.',1);

INSERT INTO `health_records` (`livestock_id`,`record_type`,`description`,`medication`,`vet_name`,`cost`,`treatment_date`,`next_due_date`) VALUES
(1,'vaccination','Annual foot and mouth disease vaccination','FMD Vaccine','Dr. Nii Armah',85.00,'2025-11-12','2026-11-12'),
(6,'treatment','Mastitis treatment - left quarter inflammation','Penstrep injection','Dr. Nii Armah',240.00,'2026-07-28','2026-08-18'),
(3,'checkup','Pregnancy confirmation and general examination',NULL,'Dr. Nii Armah',120.00,'2026-06-15','2026-09-15'),
(2,'deworming','Routine deworming of breeding bull','Albendazole',NULL,45.00,'2026-05-20','2026-11-20'),
(9,'checkup','Pregnancy scan for doe',NULL,'Dr. Yaa Frimpong',60.00,'2026-07-02','2026-09-02'),
(14,'vaccination','Newcastle disease vaccination for layer batch','NDV Lasota','Dr. Yaa Frimpong',310.00,'2026-06-05','2026-09-05'),
(15,'vaccination','Gumboro vaccination for layer batch B','IBD Vaccine','Dr. Yaa Frimpong',280.00,'2026-06-20','2026-09-20'),
(19,'checkup','Pre-farrowing health check on sow',NULL,'Dr. Nii Armah',95.00,'2026-07-20','2026-08-25'),
(13,'treatment','Quarantine observation and parasite control','Ivermectin','Dr. Yaa Frimpong',70.00,'2026-08-02','2026-08-20'),
(4,'deworming','Herd deworming programme','Levamisole',NULL,55.00,'2026-04-11','2026-10-11'),
(12,'vaccination','PPR vaccination for sheep flock','PPR Vaccine','Dr. Yaa Frimpong',90.00,'2026-03-08','2027-03-08'),
(17,'checkup','Weekly broiler weight and health monitoring',NULL,NULL,0.00,'2026-08-08','2026-08-15');

INSERT INTO `production_records` (`category_id`,`product`,`quantity`,`unit`,`record_date`,`notes`,`recorded_by`) VALUES
(1,'Milk',82.50,'litres','2026-08-14','Morning and evening milking',3),
(1,'Milk',78.20,'litres','2026-08-13',NULL,3),
(1,'Milk',85.00,'litres','2026-08-12',NULL,3),
(1,'Milk',80.40,'litres','2026-08-11',NULL,3),
(1,'Milk',79.90,'litres','2026-08-10',NULL,3),
(1,'Milk',83.10,'litres','2026-08-09',NULL,3),
(1,'Milk',81.70,'litres','2026-08-08',NULL,3),
(4,'Eggs',392.00,'pieces','2026-08-14','Batch A and B combined',3),
(4,'Eggs',381.00,'pieces','2026-08-13',NULL,3),
(4,'Eggs',405.00,'pieces','2026-08-12',NULL,3),
(4,'Eggs',376.00,'pieces','2026-08-11',NULL,3),
(4,'Eggs',398.00,'pieces','2026-08-10',NULL,3),
(4,'Eggs',388.00,'pieces','2026-08-09',NULL,3),
(4,'Eggs',401.00,'pieces','2026-08-08',NULL,3);

INSERT INTO `fields` (`id`,`name`,`size_acres`,`soil_type`,`location`,`status`) VALUES
(1,'North Block',12.50,'loamy','Northern boundary, near the borehole','cultivated'),
(2,'River Plot',8.00,'silt','Along the Oda river bank','cultivated'),
(3,'Hilltop Field',6.25,'sandy','Eastern slope','cultivated'),
(4,'South Valley',15.00,'clay','Southern low lands','cultivated'),
(5,'Homestead Garden',2.75,'loamy','Behind the farm house','cultivated'),
(6,'West Extension',10.00,'loamy','Newly cleared western section','fallow');

INSERT INTO `crops` (`crop_name`,`variety`,`field_id`,`area_planted`,`planting_date`,`expected_harvest`,`expected_yield`,`status`,`input_cost`,`notes`,`created_by`) VALUES
('Maize','Obatanpa',1,10.00,'2026-04-05','2026-08-20',18000.00,'ready',6200.00,'Major season planting. Good rains this year.',2),
('Cassava','Ampong',4,12.00,'2025-09-12','2026-09-30',36000.00,'growing',5400.00,'Twelve month variety.',2),
('Tomato','Pectomech',5,2.00,'2026-05-18','2026-08-25',4800.00,'flowering',3100.00,'Drip irrigated.',2),
('Rice','Jasmine 85',2,7.50,'2026-05-02','2026-09-10',12000.00,'growing',7800.00,'Lowland paddy along the river.',2),
('Pepper','Legon 18',5,0.75,'2026-06-01','2026-09-15',1500.00,'growing',1200.00,NULL,2),
('Plantain','Apantu',3,5.00,'2025-06-20','2026-08-30',9000.00,'ready',4300.00,'Suckers from previous cycle.',1),
('Cowpea','Videza',1,2.50,'2026-06-10','2026-09-05',1800.00,'growing',900.00,'Nitrogen fixing rotation crop.',2),
('Maize','Obatanpa',4,3.00,'2025-04-01','2025-08-15',5400.00,'harvested',1900.00,'Previous season - completed.',2),
('Okra','Asontem',5,0.50,'2026-03-15','2026-06-01',900.00,'harvested',400.00,'Minor season crop - completed.',2),
('Groundnut','Chinese',3,1.25,'2026-04-20','2026-08-10',1100.00,'ready',700.00,NULL,2);

INSERT INTO `harvests` (`crop_id`,`harvest_date`,`quantity`,`unit`,`quality_grade`,`revenue`,`notes`) VALUES
(8,'2025-08-16',5150.00,'kg','A',12875.00,'Sold to Ejisu market aggregator'),
(9,'2026-05-28',420.00,'kg','A',2520.00,'First picking'),
(9,'2026-06-04',380.00,'kg','B',1900.00,'Second picking'),
(9,'2026-06-01',260.00,'kg','A',1560.00,'Third picking'),
(1,'2026-08-12',3200.00,'kg','A',9600.00,'Early harvest of matured section'),
(6,'2026-08-05',1800.00,'kg','A',5400.00,'First bunch harvest'),
(10,'2026-08-09',640.00,'kg','A',3840.00,'Dried and bagged');

INSERT INTO `suppliers` (`id`,`name`,`contact_person`,`phone`,`email`,`address`) VALUES
(1,'AgriMax Ghana Ltd','Samuel Adjei','+233 30 276 4411','sales@agrimax.test','Adum, Kumasi'),
(2,'Ashanti Feeds & Supplies','Grace Owusu','+233 32 202 8890','info@ashantifeeds.test','Suame Magazine, Kumasi'),
(3,'Yara Ghana Distributors','Michael Tetteh','+233 30 277 1122','orders@yaragh.test','Spintex Road, Accra'),
(4,'VetCare Pharmaceuticals','Dr. Nii Armah','+233 24 887 2210','vetcare@pharma.test','Asokwa, Kumasi'),
(5,'Ejisu Hardware Enterprise','Kwabena Osei','+233 20 445 1177','ejisuhardware@test.com','Ejisu Township');

INSERT INTO `inventory_categories` (`id`,`name`,`icon`) VALUES
(1,'Animal Feed','feed'),
(2,'Seeds','seed'),
(3,'Fertilizer','fertilizer'),
(4,'Pesticides','spray'),
(5,'Veterinary','medical'),
(6,'Tools & Equipment','tool'),
(7,'Fuel & Lubricants','fuel');

INSERT INTO `inventory_items` (`item_name`,`sku`,`category_id`,`supplier_id`,`quantity`,`unit`,`reorder_level`,`unit_cost`,`location`,`expiry_date`) VALUES
('Layer Mash','FD-1001',1,2,42.00,'bags',20.00,185.00,'Feed Store A','2026-12-31'),
('Broiler Starter','FD-1002',1,2,12.00,'bags',15.00,210.00,'Feed Store A','2026-11-30'),
('Dairy Meal Concentrate','FD-1003',1,2,26.00,'bags',15.00,240.00,'Feed Store A','2027-01-15'),
('Wheat Bran','FD-1004',1,2,58.00,'bags',25.00,95.00,'Feed Store B',NULL),
('Maize Seed - Obatanpa','SD-2001',2,1,80.00,'kg',30.00,22.00,'Seed Room','2027-03-01'),
('Rice Seed - Jasmine 85','SD-2002',2,1,45.00,'kg',25.00,18.00,'Seed Room','2027-02-01'),
('Tomato Seed - Pectomech','SD-2003',2,1,3.50,'kg',2.00,320.00,'Seed Room','2026-10-01'),
('NPK 15-15-15','FT-3001',3,3,34.00,'bags',20.00,320.00,'Chemical Store','2028-01-01'),
('Urea 46%','FT-3002',3,3,18.00,'bags',20.00,290.00,'Chemical Store','2028-01-01'),
('Organic Compost','FT-3003',3,NULL,120.00,'bags',40.00,45.00,'Compost Yard',NULL),
('Glyphosate Herbicide','PS-4001',4,1,22.00,'litres',10.00,78.00,'Chemical Store','2027-06-30'),
('Lambda Cyhalothrin','PS-4002',4,1,8.00,'litres',10.00,110.00,'Chemical Store','2027-04-15'),
('Ivermectin Injection','VT-5001',5,4,14.00,'vials',8.00,65.00,'Vet Cabinet','2026-09-30'),
('Newcastle Vaccine','VT-5002',5,4,6.00,'doses',10.00,150.00,'Vet Fridge','2026-10-15'),
('Penstrep Antibiotic','VT-5003',5,4,11.00,'vials',6.00,88.00,'Vet Cabinet','2027-01-20'),
('Knapsack Sprayer 16L','TL-6001',6,5,6.00,'units',3.00,420.00,'Tool Shed',NULL),
('Cutlass / Machete','TL-6002',6,5,24.00,'units',10.00,55.00,'Tool Shed',NULL),
('Wheelbarrow','TL-6003',6,5,5.00,'units',3.00,380.00,'Tool Shed',NULL),
('Irrigation Pipe 2 inch','TL-6004',6,5,90.00,'metres',30.00,32.00,'Tool Shed',NULL),
('Diesel','FL-7001',7,NULL,180.00,'litres',100.00,15.50,'Fuel Bay',NULL),
('Engine Oil 20W-50','FL-7002',7,5,9.00,'litres',12.00,68.00,'Fuel Bay','2029-01-01');

INSERT INTO `inventory_movements` (`item_id`,`type`,`quantity`,`reference`,`note`,`user_id`,`created_at`) VALUES
(1,'in',60.00,'PO-2026-041','Monthly feed restock',2,'2026-08-01 09:15:00'),
(1,'out',18.00,'REQ-0912','Issued to poultry house',3,'2026-08-09 07:40:00'),
(2,'out',8.00,'REQ-0913','Broiler batch C feeding',3,'2026-08-10 07:30:00'),
(8,'out',6.00,'REQ-0914','Top dressing North Block maize',2,'2026-08-04 11:05:00'),
(13,'out',2.00,'REQ-0915','Quarantine treatment SH-003',3,'2026-08-02 15:20:00'),
(20,'in',200.00,'PO-2026-042','Fuel delivery for tractor',2,'2026-08-06 13:00:00'),
(20,'out',20.00,'REQ-0916','Tractor land preparation',3,'2026-08-11 08:00:00'),
(3,'in',30.00,'PO-2026-043','Dairy concentrate restock',2,'2026-08-07 10:25:00');

-- Finance: twelve months of trading history
INSERT INTO `transactions` (`type`,`category`,`amount`,`description`,`reference_no`,`payment_method`,`transaction_date`,`recorded_by`) VALUES
('income','Crop Sales',12875.00,'Maize harvest sold to Ejisu aggregator','INV-2025-088','bank','2025-09-02',1),
('income','Livestock Sales',8400.00,'Sale of 6 matured goats','INV-2025-089','mobile_money','2025-09-18',1),
('expense','Feed',9600.00,'Bulk feed purchase for the quarter','PO-2025-101','bank','2025-09-05',2),
('expense','Labour',14200.00,'September staff salaries','PAY-2025-09','bank','2025-09-28',1),
('income','Dairy',6200.00,'Milk supply to Kumasi dairy','INV-2025-090','bank','2025-10-03',1),
('income','Poultry',7800.00,'Egg sales - October','INV-2025-091','cash','2025-10-20',2),
('expense','Veterinary',2450.00,'Vaccination programme','VET-2025-33','cash','2025-10-12',2),
('expense','Labour',14200.00,'October staff salaries','PAY-2025-10','bank','2025-10-28',1),
('income','Dairy',6450.00,'Milk supply to Kumasi dairy','INV-2025-092','bank','2025-11-04',1),
('income','Poultry',8100.00,'Egg sales - November','INV-2025-093','mobile_money','2025-11-21',2),
('expense','Fertilizer',7300.00,'NPK and urea for the season','PO-2025-104','bank','2025-11-08',2),
('expense','Labour',14200.00,'November staff salaries','PAY-2025-11','bank','2025-11-28',1),
('income','Livestock Sales',15600.00,'Christmas season livestock sales','INV-2025-094','cash','2025-12-15',1),
('income','Dairy',6800.00,'Milk supply to Kumasi dairy','INV-2025-095','bank','2025-12-04',1),
('expense','Labour',18400.00,'December salaries and bonuses','PAY-2025-12','bank','2025-12-22',1),
('expense','Utilities',3200.00,'Electricity and water - Q4','UTL-2025-04','bank','2025-12-30',2),
('income','Dairy',6100.00,'Milk supply to Kumasi dairy','INV-2026-001','bank','2026-01-06',1),
('income','Poultry',7400.00,'Egg sales - January','INV-2026-002','mobile_money','2026-01-22',2),
('expense','Feed',10200.00,'Quarterly feed purchase','PO-2026-005','bank','2026-01-09',2),
('expense','Labour',14800.00,'January staff salaries','PAY-2026-01','bank','2026-01-28',1),
('income','Dairy',6300.00,'Milk supply to Kumasi dairy','INV-2026-003','bank','2026-02-05',1),
('income','Poultry',7900.00,'Egg sales - February','INV-2026-004','cash','2026-02-19',2),
('expense','Equipment',12500.00,'Irrigation pump and piping','PO-2026-011','bank','2026-02-14',1),
('expense','Labour',14800.00,'February staff salaries','PAY-2026-02','bank','2026-02-26',1),
('income','Dairy',6550.00,'Milk supply to Kumasi dairy','INV-2026-005','bank','2026-03-04',1),
('income','Poultry',8250.00,'Egg sales - March','INV-2026-006','mobile_money','2026-03-20',2),
('expense','Seeds',4800.00,'Major season seed purchase','PO-2026-018','bank','2026-03-12',2),
('expense','Labour',14800.00,'March staff salaries','PAY-2026-03','bank','2026-03-27',1),
('income','Dairy',6700.00,'Milk supply to Kumasi dairy','INV-2026-007','bank','2026-04-03',1),
('income','Poultry',8050.00,'Egg sales - April','INV-2026-008','cash','2026-04-21',2),
('expense','Fertilizer',8600.00,'Basal fertilizer application','PO-2026-022','bank','2026-04-10',2),
('expense','Fuel',3400.00,'Diesel for land preparation','PO-2026-023','cash','2026-04-15',2),
('expense','Labour',14800.00,'April staff salaries','PAY-2026-04','bank','2026-04-28',1),
('income','Dairy',6900.00,'Milk supply to Kumasi dairy','INV-2026-009','bank','2026-05-05',1),
('income','Poultry',8300.00,'Egg sales - May','INV-2026-010','mobile_money','2026-05-20',2),
('income','Crop Sales',2520.00,'Okra first picking sold','INV-2026-011','cash','2026-05-28',2),
('expense','Feed',10800.00,'Quarterly feed purchase','PO-2026-028','bank','2026-05-08',2),
('expense','Labour',15200.00,'May staff salaries','PAY-2026-05','bank','2026-05-28',1),
('income','Dairy',7100.00,'Milk supply to Kumasi dairy','INV-2026-012','bank','2026-06-04',1),
('income','Poultry',8450.00,'Egg sales - June','INV-2026-013','cash','2026-06-19',2),
('income','Crop Sales',3460.00,'Okra second and third picking','INV-2026-014','cash','2026-06-06',2),
('expense','Veterinary',3100.00,'Mid year vaccination round','VET-2026-12','bank','2026-06-10',2),
('expense','Labour',15200.00,'June staff salaries','PAY-2026-06','bank','2026-06-26',1),
('income','Dairy',7250.00,'Milk supply to Kumasi dairy','INV-2026-015','bank','2026-07-03',1),
('income','Poultry',8600.00,'Egg sales - July','INV-2026-016','mobile_money','2026-07-21',2),
('expense','Pesticides',2900.00,'Herbicide and insecticide restock','PO-2026-035','bank','2026-07-09',2),
('expense','Labour',15200.00,'July staff salaries','PAY-2026-07','bank','2026-07-28',1),
('expense','Maintenance',4600.00,'Tractor servicing and repairs','MNT-2026-07','cash','2026-07-16',1),
('income','Crop Sales',9600.00,'Early maize harvest - North Block','INV-2026-017','bank','2026-08-12',1),
('income','Crop Sales',5400.00,'Plantain first bunch harvest','INV-2026-018','cash','2026-08-05',2),
('income','Crop Sales',3840.00,'Groundnut sale - dried and bagged','INV-2026-019','mobile_money','2026-08-09',2),
('income','Dairy',3600.00,'Milk supply - first half of August','INV-2026-020','bank','2026-08-14',1),
('income','Poultry',4300.00,'Egg sales - first half of August','INV-2026-021','cash','2026-08-13',2),
('expense','Feed',11100.00,'August feed restock','PO-2026-041','bank','2026-08-01',2),
('expense','Fuel',3100.00,'Diesel delivery','PO-2026-042','cash','2026-08-06',2),
('expense','Veterinary',1850.00,'Treatment and routine checks','VET-2026-19','cash','2026-08-08',2);

INSERT INTO `tasks` (`title`,`description`,`category`,`assigned_to`,`priority`,`status`,`due_date`,`completed_at`,`created_by`) VALUES
('Complete maize harvest - North Block','Harvest the remaining 7 acres of matured maize and transport to the drying floor.','harvest',4,'urgent','pending','2026-08-20',NULL,2),
('Vaccinate layer batch B','Administer the scheduled Gumboro booster to all birds in batch B.','livestock',6,'high','in_progress','2026-08-18',NULL,2),
('Repair irrigation line - Homestead Garden','Section near the tomato beds is leaking. Replace the damaged 2 inch coupling.','maintenance',7,'high','pending','2026-08-17',NULL,2),
('Weekly milk yield reconciliation','Cross check recorded milk yields against dairy delivery notes.','livestock',2,'medium','pending','2026-08-16',NULL,1),
('Weed control - Rice paddy','Manual weeding of the River Plot rice before booting stage.','crops',5,'medium','in_progress','2026-08-19',NULL,2),
('Restock broiler starter feed','Stock has fallen below reorder level. Raise purchase order with Ashanti Feeds.','general',2,'urgent','pending','2026-08-16',NULL,1),
('Release SH-003 from quarantine','Complete the 14 day observation and clear the ram to join the flock.','livestock',6,'medium','pending','2026-08-20',NULL,2),
('Service the tractor','Routine 250 hour service - oil, filters and greasing.','maintenance',7,'low','completed','2026-07-16','2026-07-16 16:30:00',1),
('Apply top dressing - North Block','Second urea application on the maize crop.','crops',5,'high','completed','2026-08-04','2026-08-04 12:10:00',2),
('Fence repair - South Valley','Replace three broken posts on the eastern boundary.','maintenance',7,'low','pending','2026-08-25',NULL,2),
('Prepare West Extension for planting','Ploughing and harrowing ahead of the minor season.','crops',5,'medium','pending','2026-08-28',NULL,2),
('Deworm the goat herd','Administer routine dewormer to all goats.','livestock',3,'medium','pending','2026-08-22',NULL,2),
('Egg collection and grading','Daily egg collection, cleaning and grading before dispatch.','livestock',8,'high','in_progress','2026-08-15',NULL,2),
('Update supplier price list','Collect current quotations from all registered suppliers.','general',2,'low','pending','2026-08-30',NULL,1),
('Harvest plantain - Hilltop Field','Second round of bunch harvesting.','harvest',4,'medium','pending','2026-08-24',NULL,2);

INSERT INTO `activity_log` (`user_id`,`module`,`action`,`description`,`ip_address`,`created_at`) VALUES
(1,'auth','login','Kwame Mensah signed in','127.0.0.1','2026-08-14 07:12:00'),
(2,'livestock','create','Added new livestock record PL-003','127.0.0.1','2026-08-13 09:40:00'),
(2,'inventory','update','Recorded stock movement for Layer Mash','127.0.0.1','2026-08-09 07:41:00'),
(1,'finance','create','Recorded income of GHS 9,600.00','127.0.0.1','2026-08-12 15:22:00'),
(3,'tasks','update','Marked task as in progress','127.0.0.1','2026-08-11 08:05:00'),
(2,'crops','update','Updated crop status to ready for Maize','127.0.0.1','2026-08-10 11:30:00'),
(1,'employees','create','Added new employee record','127.0.0.1','2026-08-05 14:15:00'),
(2,'health','create','Logged treatment record for CT-006','127.0.0.1','2026-07-28 16:02:00');
