<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class PendingChangesService
{
    private const TABLE = '#__jmcp_pending_changes';

    public function create(string $toolName, array $params, string $description = ''): array
    {
        $row = (object) [
            'created'     => Factory::getDate()->toSql(),
            'tool_name'   => substr($toolName, 0, 128),
            'params'      => json_encode($params, JSON_UNESCAPED_UNICODE),
            'description' => substr($description, 0, 512),
            'status'      => 'pending',
            'created_by'  => Factory::getUser()->id ?: 0,
        ];

        $db = Factory::getDbo();
        $db->insertObject(self::TABLE, $row);

        return [
            'id'      => (int) $db->insertid(),
            'status'  => 'pending',
            'message' => 'Change queued for administrator approval.',
        ];
    }

    /** @return array<int, object> */
    public function listPending(int $limit = 25): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
            ->order($db->quoteName('id') . ' DESC');

        return $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];
    }

    public function approve(int $id, callable $executor): array
    {
        $change = $this->getById($id);
        if ($change->status !== 'pending') {
            throw new \RuntimeException('Change is not pending.');
        }

        $params = json_decode($change->params, true) ?: [];
        $result = $executor($change->tool_name, $params);

        $this->updateStatus($id, 'approved');

        return ['id' => $id, 'status' => 'approved', 'result' => $result];
    }

    public function reject(int $id, string $reason = ''): array
    {
        $this->updateStatus($id, 'rejected', $reason);
        return ['id' => $id, 'status' => 'rejected', 'message' => $reason ?: 'Rejected by administrator.'];
    }

    private function getById(int $id): object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('id') . ' = ' . $id);

        $row = $db->setQuery($query)->loadObject();
        if (!$row) {
            throw new \RuntimeException(sprintf('Pending change %d not found.', $id));
        }
        return $row;
    }

    private function updateStatus(int $id, string $status, string $note = ''): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName(self::TABLE))
            ->set($db->quoteName('status') . ' = ' . $db->quote($status))
            ->set($db->quoteName('resolved') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('resolved_by') . ' = ' . (Factory::getUser()->id ?: 0))
            ->where($db->quoteName('id') . ' = ' . $id);

        if ($note !== '') {
            $query->set($db->quoteName('note') . ' = ' . $db->quote(substr($note, 0, 512)));
        }

        $db->setQuery($query)->execute();
    }
}
