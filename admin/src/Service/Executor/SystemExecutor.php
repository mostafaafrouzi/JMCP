<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version;

class SystemExecutor
{
    public function runCacheClean(array $params): array
    {
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        exec(escapeshellarg($php) . ' ' . $cli . ' cache:clean 2>&1', $output, $code);

        return ['exit_code' => $code, 'output' => implode("\n", $output), 'message' => 'Cache clean executed.'];
    }

    public function checkCoreUpdates(array $params): array
    {
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        exec(escapeshellarg($php) . ' ' . $cli . ' core:check-updates 2>&1', $output, $code);

        return ['exit_code' => $code, 'output' => implode("\n", $output)];
    }

    public function getSiteHealthExtended(array $params): array
    {
        $version = new Version();
        $config = Factory::getApplication()->getConfig();
        $db = Factory::getDbo();

        return [
            'status'          => 'ok',
            'joomla_version'  => $version->getShortVersion(),
            'php_version'     => PHP_VERSION,
            'db_version'      => $db->getVersion(),
            'debug_mode'      => (bool) $config->get('debug'),
            'cache_enabled'   => (bool) $config->get('caching'),
            'sef_enabled'     => (bool) $config->get('sef'),
            'gzip_enabled'    => (bool) $config->get('gzip'),
            'error_reporting' => (string) $config->get('error_reporting'),
            'timestamp'       => Factory::getDate('now', 'UTC')->toSql(true),
        ];
    }

    public function getPerformanceHints(array $params): array
    {
        $hints = [];
        $config = Factory::getApplication()->getConfig();

        if (!(bool) $config->get('caching')) {
            $hints[] = 'Enable conservative caching in Global Configuration.';
        }
        if (!(bool) $config->get('gzip')) {
            $hints[] = 'Enable Gzip page compression.';
        }
        if ((bool) $config->get('debug')) {
            $hints[] = 'Disable debug mode on production sites.';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('COUNT(*)')->from('#__content')->where('state = 1');
        $articles = (int) $db->setQuery($query)->loadResult();
        if ($articles > 500) {
            $hints[] = 'Consider cache plugins and database indexing for large content volume.';
        }

        return ['hints' => $hints, 'article_count' => $articles];
    }

    public function finderRebuildIndex(array $params): array
    {
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        exec(escapeshellarg($php) . ' ' . $cli . ' finder:index 2>&1', $output, $code);

        return [
            'exit_code' => $code,
            'output'    => implode("\n", $output),
            'message'   => $code === 0 ? 'Finder index rebuilt.' : 'Finder index command finished with errors.',
        ];
    }

    public function applyJoomlaUpdate(array $params): array
    {
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        exec(escapeshellarg($php) . ' ' . $cli . ' core:update 2>&1', $output, $code);

        return [
            'exit_code' => $code,
            'output'    => implode("\n", $output),
            'message'   => $code === 0 ? 'Joomla update applied.' : 'Update command finished with errors.',
        ];
    }

    public function listSchedulerTasks(array $params): array
    {
        $db = Factory::getDbo();
        if (!$this->tableExists('#__scheduler_tasks')) {
            return ['tasks' => [], 'message' => 'Scheduler not available.'];
        }

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'type', 'execution_rules', 'state', 'last_execution', 'next_execution'])
            ->from('#__scheduler_tasks')
            ->order('id ASC');

        return ['tasks' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function runSchedulerTask(array $params): array
    {
        $taskId = (int) ($params['task_id'] ?? 0);
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        $cmd = escapeshellarg($php) . ' ' . $cli . ' scheduler:run';

        if ($taskId > 0) {
            $cmd .= ' --id=' . $taskId;
        }

        exec($cmd . ' 2>&1', $output, $code);

        return [
            'task_id'   => $taskId ?: null,
            'exit_code' => $code,
            'output'    => implode("\n", $output),
            'message'   => 'Scheduler task executed.',
        ];
    }

    private function tableExists(string $table): bool
    {
        $db = Factory::getDbo();
        return in_array($db->replacePrefix($table), $db->getTableList() ?: [], true);
    }
}
