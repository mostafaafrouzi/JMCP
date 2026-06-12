<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Ensures JMCP database tables exist on install/update.
 */
class DatabaseInstaller
{
    public function ensureTables(): void
    {
        $db = Factory::getDbo();
        $statements = $this->getCreateStatements();

        foreach ($statements as $sql) {
            try {
                $db->setQuery($sql)->execute();
            } catch (\Throwable $e) {
                // Continue — CREATE IF NOT EXISTS should be safe
            }
        }
    }

    /** @return string[] */
    private function getCreateStatements(): array
    {
        $prefix = Factory::getDbo()->getPrefix();

        return [
            "CREATE TABLE IF NOT EXISTS `{$prefix}jmcp_request_log` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `{$prefix}jmcp_audit_log` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `created` datetime NOT NULL,
              `tool_name` varchar(128) NOT NULL DEFAULT '',
              `action` varchar(64) NOT NULL DEFAULT '',
              `details` mediumtext,
              `dry_run` tinyint NOT NULL DEFAULT 0,
              `user_id` int NOT NULL DEFAULT 0,
              `client_ip` varchar(45) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `{$prefix}jmcp_pending_changes` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `created` datetime NOT NULL,
              `tool_name` varchar(128) NOT NULL DEFAULT '',
              `params` mediumtext,
              `description` varchar(512) NOT NULL DEFAULT '',
              `status` varchar(20) NOT NULL DEFAULT 'pending',
              `created_by` int NOT NULL DEFAULT 0,
              `resolved` datetime DEFAULT NULL,
              `resolved_by` int NOT NULL DEFAULT 0,
              `note` varchar(512) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `{$prefix}jmcp_webhook_log` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `created` datetime NOT NULL,
              `event` varchar(64) NOT NULL DEFAULT '',
              `http_code` smallint NOT NULL DEFAULT 0,
              `payload` mediumtext,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `{$prefix}jmcp_memory` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `mem_key` varchar(191) NOT NULL DEFAULT '',
              `mem_value` mediumtext,
              `context` varchar(64) NOT NULL DEFAULT 'global',
              `created` datetime NOT NULL,
              `modified` datetime NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_key` (`mem_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
