-- ------------------------------------------------------------
-- Makeup Meetings module
-- Run once on the rotary_attendance database (e.g. via phpMyAdmin → SQL)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `makeup_meetings` (
  `id`               int UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`        int UNSIGNED NOT NULL,
  `meeting_date`     date         NOT NULL,
  `club_visited`     varchar(150) NOT NULL,
  `district`         varchar(50)  DEFAULT NULL,
  `meeting_type`     varchar(60)  NOT NULL DEFAULT 'Club Meeting',
  `venue`            varchar(200) DEFAULT NULL,
  `notes`            text         DEFAULT NULL,
  `attachment_path`  varchar(255) DEFAULT NULL,
  `attachment_name`  varchar(255) DEFAULT NULL,
  `attachment_mime`  varchar(100) DEFAULT NULL,
  `status`           varchar(20)  NOT NULL DEFAULT 'Pending',
  `review_note`      varchar(255) DEFAULT NULL,
  `reviewed_by`      int UNSIGNED DEFAULT NULL,
  `reviewed_at`      datetime     DEFAULT NULL,
  `created_by`       int UNSIGNED DEFAULT NULL,
  `created_at`       timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_makeup_member` (`member_id`),
  KEY `idx_makeup_date`   (`meeting_date`),
  KEY `idx_makeup_status` (`status`),
  CONSTRAINT `fk_makeup_member`   FOREIGN KEY (`member_id`)   REFERENCES `members` (`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_makeup_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_makeup_creator`  FOREIGN KEY (`created_by`)  REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
