<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class AuditService
{
    private const TABLE = '#__jmcp_audit_log';

    public function log(string $toolName, string $action, array $details = [], bool $dryRun = false): void
    {
        try {
            $row = (object) [
                'created'   => Factory::getDate()->toSql(),
                'tool_name' => substr($toolName, 0, 128),
                'action'    => substr($action, 0, 64),
                'details'   => json_encode($details, JSON_UNESCAPED_UNICODE),
                'dry_run'   => $dryRun ? 1 : 0,
                'user_id'   => Factory::getUser()->id ?: 0,
                'client_ip' => substr(Factory::getApplication()->input->server->getString('REMOTE_ADDR', ''), 0, 45),
            ];

            Factory::getDbo()->insertObject(self::TABLE, $row);
        } catch (\Throwable $e) {
            // Never break main flow
        }
    }

    /** @return array<int, object> */
    public function getRecent(int $limit = 50): array
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName(self::TABLE))
                ->order($db->quoteName('id') . ' DESC');
            return $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
