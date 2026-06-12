<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class CodeExecutor
{
    private const MAX_EXECUTION_SECONDS = 30;

    /** @var string[] */
    private const BLOCKED_PHP_PATTERNS = [
        '/\bexit\s*\(/i',
        '/\bdie\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bunlink\s*\(/i',
        '/\brmdir\s*\(/i',
        '/\bchmod\s*\(/i',
        '/\bchown\s*\(/i',
    ];

    public function executePhp(array $params): array
    {
        $code = trim((string) ($params['code'] ?? ''));

        if ($code === '') {
            throw new \RuntimeException('PHP code cannot be empty.');
        }

        foreach (self::BLOCKED_PHP_PATTERNS as $pattern) {
            if (preg_match($pattern, $code)) {
                throw new \RuntimeException('This PHP construct is blocked for security reasons.');
            }
        }

        $previousLimit = ini_get('max_execution_time');
        set_time_limit(self::MAX_EXECUTION_SECONDS);

        $outputBuffer = '';
        $result = null;
        $error = null;

        try {
            ob_start();
            $result = eval($code);
            $outputBuffer = (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $error = $e->getMessage();
        } finally {
            if ($previousLimit !== false) {
                set_time_limit((int) $previousLimit);
            }
        }

        if ($error !== null) {
            throw new \RuntimeException('PHP execution error: ' . $error);
        }

        return [
            'output' => $outputBuffer,
            'return' => $this->normaliseReturnValue($result),
        ];
    }

    public function runCliCommand(array $params): array
    {
        $command = trim((string) ($params['command'] ?? ''));

        if ($command === '') {
            throw new \RuntimeException('CLI command cannot be empty.');
        }

        if (preg_match('/[;&|`$]/', $command)) {
            throw new \RuntimeException('Invalid characters in CLI command.');
        }

        $joomlaCli = JPATH_ROOT . '/cli/joomla.php';

        if (!is_file($joomlaCli)) {
            throw new \RuntimeException('Joomla CLI entry point not found.');
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $fullCommand = escapeshellarg($phpBinary) . ' ' . escapeshellarg($joomlaCli) . ' ' . $command;

        $output = [];
        $exitCode = 0;
        exec($fullCommand . ' 2>&1', $output, $exitCode);

        return [
            'command'   => $command,
            'exit_code' => $exitCode,
            'output'    => implode("\n", $output),
        ];
    }

    private function normaliseReturnValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true);
        }

        return (string) $value;
    }
}
