CREATE TABLE IF NOT EXISTS `#__jmcp_request_log` (
  `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created`     DATETIME NOT NULL,
  `method`      VARCHAR(64)  NOT NULL DEFAULT '',
  `tool_name`   VARCHAR(128) NOT NULL DEFAULT '',
  `status`      VARCHAR(20)  NOT NULL DEFAULT '',
  `error_code`  INT(11)      NULL DEFAULT NULL,
  `http_status` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `client_ip`   VARCHAR(45)  NOT NULL DEFAULT '',
  `context`     VARCHAR(10)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`),
  KEY `idx_method` (`method`),
  KEY `idx_tool` (`tool_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__jmcp_audit_log` (
  `id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created`   DATETIME NOT NULL,
  `tool_name` VARCHAR(128) NOT NULL DEFAULT '',
  `action`    VARCHAR(64)  NOT NULL DEFAULT '',
  `details`   MEDIUMTEXT,
  `dry_run`   TINYINT(1) NOT NULL DEFAULT 0,
  `user_id`   INT(11) NOT NULL DEFAULT 0,
  `client_ip` VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`),
  KEY `idx_tool` (`tool_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__jmcp_pending_changes` (
  `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created`     DATETIME NOT NULL,
  `tool_name`   VARCHAR(128) NOT NULL DEFAULT '',
  `params`      MEDIUMTEXT,
  `description` VARCHAR(512) NOT NULL DEFAULT '',
  `status`      VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_by`  INT(11) NOT NULL DEFAULT 0,
  `resolved`    DATETIME NULL DEFAULT NULL,
  `resolved_by` INT(11) NOT NULL DEFAULT 0,
  `note`        VARCHAR(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__jmcp_webhook_log` (
  `id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created`   DATETIME NOT NULL,
  `event`     VARCHAR(64) NOT NULL DEFAULT '',
  `http_code` SMALLINT(5) NOT NULL DEFAULT 0,
  `payload`   MEDIUMTEXT,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__jmcp_memory` (
  `id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mem_key`   VARCHAR(191) NOT NULL DEFAULT '',
  `mem_value` MEDIUMTEXT,
  `context`   VARCHAR(64)  NOT NULL DEFAULT 'global',
  `created`   DATETIME NOT NULL,
  `modified`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`mem_key`),
  KEY `idx_context` (`context`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
