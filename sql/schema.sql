-- =====================================================================
--  College Sports Faculty Portal — Database Schema
--  MySQL 5.7+ / MariaDB 10.3+  |  InnoDB  |  utf8mb4
-- =====================================================================

SET FOREIGN_KEY_CHECKS=0;
SET FOREIGN_KEY_CHECKS=1;

-- ---------------------------------------------------------------------
-- 1. departments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(32)  NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `full_name`     VARCHAR(200) NOT NULL,
    `icon`          VARCHAR(64)  NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `display_order` SMALLINT     NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dept_code` (`code`),
    KEY `idx_dept_active` (`is_active`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. faculty  (SUPER_ADMIN + FACULTY users)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `faculty`;
CREATE TABLE `faculty` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)  NOT NULL,
    `email`         VARCHAR(160) NOT NULL,
    `full_name`     VARCHAR(160) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('SUPER_ADMIN','FACULTY') NOT NULL DEFAULT 'FACULTY',
    `phone`         VARCHAR(20)  NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `must_reset_pw` TINYINT(1)   NOT NULL DEFAULT 0,
    `last_login_at` TIMESTAMP    NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_faculty_username` (`username`),
    UNIQUE KEY `uq_faculty_email` (`email`),
    KEY `idx_faculty_role` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2b. faculty_departments (many-to-many faculty access)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `faculty_departments`;
CREATE TABLE `faculty_departments` (
    `faculty_id`    INT UNSIGNED     NOT NULL,
    `department_id` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`faculty_id`, `department_id`),
    CONSTRAINT `fk_fd_faculty`
        FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fd_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. students
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_no` VARCHAR(40)  NOT NULL,
    `roll_no`       VARCHAR(40)  NULL,
    `full_name`     VARCHAR(160) NOT NULL,
    `mother_name`   VARCHAR(160) NULL,
    `dob`           DATE         NULL,
    `gender`        ENUM('Male','Female','Other') NULL,
    `blood_group`   ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NULL,
    `email`         VARCHAR(160) NULL,
    `mobile`        VARCHAR(20)  NULL,
    `parent_phone`  VARCHAR(20)  NULL,
    `address`       TEXT         NULL,
    `department_id` TINYINT UNSIGNED NOT NULL,
    `program`       VARCHAR(120) NULL,
    `academic_year` VARCHAR(10)  NULL,
    `study_year`    ENUM('First','Second','Third','Final') NULL,
    `sport_1`       VARCHAR(80)  NULL,
    `sport_2`       VARCHAR(80)  NULL,
    `achievements`  TEXT         NULL,
    `sports_history` TEXT        NULL,
    `photo_path`    VARCHAR(255) NULL,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_enrollment` (`enrollment_no`),
    KEY `idx_student_dept` (`department_id`),
    KEY `idx_student_name` (`full_name`),
    KEY `idx_student_year` (`academic_year`),
    KEY `idx_student_dept_year` (`department_id`,`academic_year`),
    CONSTRAINT `fk_student_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_student_creator` FOREIGN KEY (`created_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. achievements
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `achievements`;
CREATE TABLE `achievements` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`    INT UNSIGNED NULL,
    `title`         VARCHAR(200) NOT NULL,
    `description`   TEXT         NULL,
    `event_name`    VARCHAR(160) NULL,
    `level`         ENUM('College','University','State','National','International') NULL,
    `position`      VARCHAR(40)  NULL,
    `event_date`    DATE         NULL,
    `image_path`    VARCHAR(255) NULL,
    `is_published`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ach_student` (`student_id`),
    KEY `idx_ach_published` (`is_published`,`event_date`),
    CONSTRAINT `fk_ach_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. notices
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(255) NOT NULL,
    `category`      VARCHAR(60)  NULL,
    `summary`       TEXT         NULL,
    `body`          MEDIUMTEXT   NULL,
    `attachment`    VARCHAR(255) NULL,
    `notice_date`   DATE         NOT NULL,
    `is_published`  TINYINT(1)   NOT NULL DEFAULT 1,
    `posted_by`     INT UNSIGNED NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notice_published` (`is_published`,`notice_date`),
    CONSTRAINT `fk_notice_poster` FOREIGN KEY (`posted_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. hero_settings  (single-row config)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `hero_settings`;
CREATE TABLE `hero_settings` (
    `id`                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `headline`            VARCHAR(200) NOT NULL,
    `subheadline`         VARCHAR(255) NULL,
    `description`         TEXT         NULL,
    `background_image`    VARCHAR(255) NULL,
    `primary_button_text` VARCHAR(80)  NULL,
    `primary_button_link` VARCHAR(255) NULL,
    `secondary_button_text` VARCHAR(80) NULL,
    `secondary_button_link` VARCHAR(255) NULL,
    `updated_by`          INT UNSIGNED NULL,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_hero_updater` FOREIGN KEY (`updated_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. college_settings  (single-row identity config)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `college_settings`;
CREATE TABLE `college_settings` (
    `id`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `name`         VARCHAR(200) NOT NULL,
    `trust_name`   VARCHAR(200) NULL,
    `affiliation`  VARCHAR(255) NULL,
    `logo_path`    VARCHAR(255) NULL,
    `tagline`      VARCHAR(255) NULL,
    `address`      TEXT         NULL,
    `phone`        VARCHAR(40)  NULL,
    `email`        VARCHAR(160) NULL,
    `map_embed`    TEXT         NULL,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. login_attempts  (rate limiting)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(64)  NOT NULL,
    `ip`          VARBINARY(16) NOT NULL,
    `user_agent`  VARCHAR(255) NULL,
    `success`     TINYINT(1)   NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attempt_ip_time` (`ip`,`attempted_at`),
    KEY `idx_attempt_user_time` (`username`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. password_resets  (forgot-password tokens)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` TIMESTAMP    NOT NULL,
    `used_at`    TIMESTAMP    NULL,
    `ip`         VARBINARY(16) NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reset_faculty` (`faculty_id`),
    KEY `idx_reset_token` (`token_hash`),
    CONSTRAINT `fk_reset_faculty` FOREIGN KEY (`faculty_id`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. provisional_entries
-- ---------------------------------------------------------------------
CREATE TABLE `provisional_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_name`       VARCHAR(80)  NOT NULL,
    `event_label`     VARCHAR(120) NOT NULL DEFAULT '',
    `event_date`      DATE         NULL,
    `academic_year`   VARCHAR(10)  NULL,
    `student_id`      INT UNSIGNED NOT NULL,
    `notes`           VARCHAR(500) NULL,
    `is_provisional`  TINYINT(1)   NOT NULL DEFAULT 1,
    `added_by`        INT UNSIGNED NOT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_prov_student_list` (`game_name`,`event_label`,`academic_year`,`student_id`),
    KEY `idx_prov_game_event` (`game_name`,`event_label`,`academic_year`),
    KEY `idx_prov_student` (`student_id`),
    KEY `idx_prov_added_by` (`added_by`),
    CONSTRAINT `fk_prov_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_prov_added_by` FOREIGN KEY (`added_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. department document requirements and student documents
-- ---------------------------------------------------------------------
CREATE TABLE `dept_document_requirements` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id`      TINYINT UNSIGNED NOT NULL,
    `document_name`      VARCHAR(100) NOT NULL,
    `is_required`        TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_mime_types` VARCHAR(255) NOT NULL DEFAULT 'application/pdf,image/jpeg,image/png',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dept_document` (`department_id`,`document_name`),
    CONSTRAINT `fk_req_dept` FOREIGN KEY (`department_id`)
        REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_documents` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     INT UNSIGNED NOT NULL,
    `requirement_id` INT UNSIGNED NOT NULL,
    `file_path`      VARCHAR(255) NOT NULL,
    `uploaded_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_requirement` (`student_id`,`requirement_id`),
    CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_doc_req` FOREIGN KEY (`requirement_id`)
        REFERENCES `dept_document_requirements`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. final teams
-- ---------------------------------------------------------------------
CREATE TABLE `final_teams` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_name`     VARCHAR(80)  NOT NULL,
    `event_label`   VARCHAR(120) NOT NULL,
    `academic_year` VARCHAR(10)  NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `roll_no`       VARCHAR(40)  NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `added_by`      INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_final_student_list` (`game_name`,`event_label`,`academic_year`,`student_id`),
    KEY `idx_final_game_event` (`game_name`,`event_label`,`academic_year`),
    KEY `idx_final_student` (`student_id`),
    CONSTRAINT `fk_final_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_final_faculty` FOREIGN KEY (`added_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. jersey forms and requests
-- ---------------------------------------------------------------------
CREATE TABLE `jersey_forms` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id`   TINYINT UNSIGNED NOT NULL,
    `game_name`       VARCHAR(80)  NOT NULL,
    `event_label`     VARCHAR(120) NOT NULL,
    `academic_year`   VARCHAR(10)  NOT NULL DEFAULT '',
    `is_open`         TINYINT(1)   NOT NULL DEFAULT 0,
    `access_token`    VARCHAR(64)  NOT NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jersey_form_team` (`department_id`,`game_name`,`event_label`,`academic_year`),
    UNIQUE KEY `uq_jersey_token` (`access_token`),
    CONSTRAINT `fk_jersey_form_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_jersey_form_faculty` FOREIGN KEY (`created_by`)
        REFERENCES `faculty`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jersey_requests` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jersey_form_id`   INT UNSIGNED NOT NULL,
    `student_id`       INT UNSIGNED NOT NULL,
    `enrollment_no`    VARCHAR(40)  NOT NULL,
    `mobile`           VARCHAR(20)  NOT NULL,
    `tshirt_size`      ENUM('XS','S','M','L','XL','XXL','3XL') NOT NULL,
    `jersey_name`      VARCHAR(30)  NOT NULL,
    `preferred_number` SMALLINT UNSIGNED NOT NULL,
    `final_number`     SMALLINT UNSIGNED NULL,
    `status`           ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `locked`           TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jersey_student` (`jersey_form_id`,`student_id`),
    KEY `idx_jersey_number` (`jersey_form_id`,`preferred_number`),
    CONSTRAINT `fk_jersey_form` FOREIGN KEY (`jersey_form_id`)
        REFERENCES `jersey_forms`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_jersey_student` FOREIGN KEY (`student_id`)
        REFERENCES `students`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. public contact messages
-- ---------------------------------------------------------------------
CREATE TABLE `contact_messages` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `email`      VARCHAR(160) NOT NULL,
    `phone`      VARCHAR(20)  NULL,
    `subject`    VARCHAR(160) NULL,
    `message`    TEXT         NOT NULL,
    `ip`         VARBINARY(16) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status_time` (`is_read`,`created_at`),
    KEY `idx_contact_ip_time` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  END OF SCHEMA (14 feature groups / 17 tables)
-- =====================================================================
