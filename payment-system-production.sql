-- Brightvision Admin - Payment System production migration
-- Select your production database before running this file.
-- This migration is non-destructive and keeps all existing payment data.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `payment_courses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `course_number` VARCHAR(50) NOT NULL,
    `course_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_courses_number` (`course_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_plans` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `course_id` INT(11) NULL,
    `student_user_id` INT(11) NULL,
    `student_name` VARCHAR(255) NOT NULL,
    `student_id_number` VARCHAR(50) NOT NULL,
    `payment_method` ENUM('full','half','quarter','scholarship','free_card') NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_by` INT(11) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payment_course` (`course_id`),
    KEY `idx_payment_student_id` (`student_id_number`),
    KEY `idx_payment_method` (`payment_method`),
    KEY `idx_payment_created_at` (`created_at`),
    CONSTRAINT `fk_payment_plan_course`
        FOREIGN KEY (`course_id`) REFERENCES `payment_courses` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_installments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payment_plan_id` INT(11) NOT NULL,
    `installment_number` INT(11) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_date` DATE NOT NULL,
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
    `paid_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_plan_installment` (`payment_plan_id`, `installment_number`),
    KEY `idx_installment_date` (`payment_date`),
    KEY `idx_installment_paid` (`is_paid`),
    CONSTRAINT `fk_installment_plan`
        FOREIGN KEY (`payment_plan_id`) REFERENCES `payment_plans` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade an earlier payment_plans table that did not yet contain course_id.
SET @course_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_plans'
      AND COLUMN_NAME = 'course_id'
);
SET @migration_sql = IF(
    @course_column_exists = 0,
    'ALTER TABLE `payment_plans` ADD COLUMN `course_id` INT(11) NULL AFTER `id`',
    'SELECT 1'
);
PREPARE payment_migration FROM @migration_sql;
EXECUTE payment_migration;
DEALLOCATE PREPARE payment_migration;

-- Add the course index when upgrading an earlier table.
SET @course_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_plans'
      AND INDEX_NAME = 'idx_payment_course'
);
SET @migration_sql = IF(
    @course_index_exists = 0,
    'ALTER TABLE `payment_plans` ADD INDEX `idx_payment_course` (`course_id`)',
    'SELECT 1'
);
PREPARE payment_migration FROM @migration_sql;
EXECUTE payment_migration;
DEALLOCATE PREPARE payment_migration;

-- Add the course relationship when upgrading an earlier table.
SET @course_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_plans'
      AND CONSTRAINT_NAME = 'fk_payment_plan_course'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @migration_sql = IF(
    @course_fk_exists = 0,
    'ALTER TABLE `payment_plans` ADD CONSTRAINT `fk_payment_plan_course` FOREIGN KEY (`course_id`) REFERENCES `payment_courses` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE payment_migration FROM @migration_sql;
EXECUTE payment_migration;
DEALLOCATE PREPARE payment_migration;

-- Optional verification. Each value should be 1.
SELECT
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_courses') AS payment_courses_ready,
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_plans') AS payment_plans_ready,
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_installments') AS payment_installments_ready;
