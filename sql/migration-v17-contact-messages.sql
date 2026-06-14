-- Migration v17: store messages submitted through the public contact form.

CREATE TABLE IF NOT EXISTS `contact_messages` (
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
