<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\Tier\FeatureGate;
use Joomla\Component\Jmcp\Administrator\Service\Tier\LicenseService;

/**
 * Persistent memory between AI sessions (Pro-tier structure).
 * Table and API exist; gated behind Pro license.
 */
class PersistentMemoryService
{
    private const TABLE = '#__jmcp_memory';
    private FeatureGate $gate;

    public function __construct(?FeatureGate $gate = null)
    {
        $this->gate = $gate ?? new FeatureGate();
    }

    public function store(string $key, string $value, string $context = 'global'): array
    {
        $this->assertPro();

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName(self::TABLE))
            ->where('mem_key = ' . $db->quote($key))
            ->where('context = ' . $db->quote($context));

        $existingId = (int) $db->setQuery($query)->loadResult();
        $now = Factory::getDate()->toSql();

        if ($existingId > 0) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName(self::TABLE))
                    ->set('mem_value = ' . $db->quote($value))
                    ->set('modified = ' . $db->quote($now))
                    ->where('id = ' . $existingId)
            )->execute();
            return ['id' => $existingId, 'key' => $key, 'message' => 'Memory updated.'];
        }

        $db->insertObject(self::TABLE, (object) [
            'mem_key'   => substr($key, 0, 191),
            'mem_value' => $value,
            'context'   => substr($context, 0, 64),
            'created'   => $now,
            'modified'  => $now,
        ]);

        return ['id' => (int) $db->insertid(), 'key' => $key, 'message' => 'Memory stored.'];
    }

    public function search(string $query, string $context = ''): array
    {
        $this->assertPro();

        $db = Factory::getDbo();
        $q = $db->getQuery(true)
            ->select(['id', 'mem_key', 'mem_value', 'context', 'modified'])
            ->from($db->quoteName(self::TABLE))
            ->where('(mem_key LIKE ' . $db->quote('%' . $db->escape($query, true) . '%')
                . ' OR mem_value LIKE ' . $db->quote('%' . $db->escape($query, true) . '%') . ')');

        if ($context !== '') {
            $q->where('context = ' . $db->quote($context));
        }

        $q->order('modified DESC');
        $db->setQuery($q, 0, 25);

        return ['results' => $db->loadAssocList() ?: []];
    }

    public function listAll(string $context = '', int $limit = 50): array
    {
        $this->assertPro();

        $db = Factory::getDbo();
        $q = $db->getQuery(true)
            ->select(['id', 'mem_key', 'context', 'modified'])
            ->from($db->quoteName(self::TABLE))
            ->order('modified DESC');

        if ($context !== '') {
            $q->where('context = ' . $db->quote($context));
        }

        $db->setQuery($q, 0, $limit);

        return ['entries' => $db->loadAssocList() ?: []];
    }

    private function assertPro(): void
    {
        if (!$this->gate->getLicenseService()->isProActive()) {
            throw new \RuntimeException('Persistent memory requires JMCP Pro.');
        }
    }
}
