-- JMCP 2.2.0 — ensure request log table exists on upgrades from early builds
CREATE TABLE IF NOT EXISTS `#__jmcp_request_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `method` varchar(64) NOT NULL DEFAULT '',
  `tool_name` varchar(128) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '',
  `error_code` int DEFAULT NULL,
  `http_status` smallint unsigned NOT NULL DEFAULT 0,
  `duration_ms` int unsigned NOT NULL DEFAULT 0,
  `client_ip` varchar(45) NOT NULL DEFAULT '',
  `context` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
