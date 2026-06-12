<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\Component\Jmcp\Administrator\Service\PersistentMemoryService;

class MemoryExecutor
{
    private PersistentMemoryService $memory;

    public function __construct(?PersistentMemoryService $memory = null)
    {
        $this->memory = $memory ?? new PersistentMemoryService();
    }

    public function memoryStore(array $params): array
    {
        return $this->memory->store(
            (string) ($params['key'] ?? ''),
            (string) ($params['value'] ?? ''),
            (string) ($params['context'] ?? 'global')
        );
    }

    public function memorySearch(array $params): array
    {
        return $this->memory->search(
            (string) ($params['query'] ?? ''),
            (string) ($params['context'] ?? '')
        );
    }

    public function memoryList(array $params): array
    {
        return $this->memory->listAll(
            (string) ($params['context'] ?? ''),
            (int) ($params['limit'] ?? 50)
        );
    }
}
