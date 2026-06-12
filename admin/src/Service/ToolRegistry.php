<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

class ToolRegistry
{
    private array $tools = [];
    private array $executors = [];

    public function __construct()
    {
        foreach (ToolDefinitions::getAll() as $tool) {
            $this->register($tool);
        }
    }

    public function register(array $tool): void
    {
        $name = $tool['name'] ?? '';
        if ($name === '') {
            return;
        }
        $this->tools[$name] = $tool;
    }

    public function setExecutor(string $name, callable $executor): void
    {
        $this->executors[$name] = $executor;
    }

    public function get(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    public function getAll(): array
    {
        return array_values($this->tools);
    }

    public function hasExecutor(string $name): bool
    {
        return isset($this->executors[$name]);
    }

    public function execute(string $name, array $params): mixed
    {
        if (!isset($this->executors[$name])) {
            throw new \RuntimeException(sprintf("Executor for tool '%s' not registered.", $name));
        }
        return ($this->executors[$name])($params);
    }
}
