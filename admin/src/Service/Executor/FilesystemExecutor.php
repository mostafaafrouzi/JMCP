<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\Component\Jmcp\Administrator\Service\PathGuard;

class FilesystemExecutor
{
    private PathGuard $guard;

    public function __construct(?PathGuard $guard = null)
    {
        $this->guard = $guard ?? new PathGuard();
    }

    public function listDirectory(array $params): array
    {
        $relative = (string) ($params['path'] ?? '');
        $absolute = $this->guard->resolve($relative);

        if (!is_dir($absolute)) {
            throw new \RuntimeException('Path is not a directory.');
        }

        $entries = [];
        $items = scandir($absolute) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $absolute . DIRECTORY_SEPARATOR . $item;
            $entries[] = [
                'name'  => $item,
                'type'  => is_dir($full) ? 'directory' : 'file',
                'size'  => is_file($full) ? filesize($full) : null,
                'path'  => $this->toRelative($full),
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path'    => $relative,
            'entries' => $entries,
        ];
    }

    public function readFile(array $params): array
    {
        $absolute = $this->guard->resolve((string) ($params['path'] ?? ''));

        if (!is_file($absolute)) {
            throw new \RuntimeException('Path is not a file.');
        }

        $offset = max(0, (int) ($params['offset'] ?? 0));
        $limit  = (int) ($params['limit'] ?? -1);
        $size   = filesize($absolute) ?: 0;

        if ($limit < 0) {
            $content = file_get_contents($absolute);
        } else {
            $handle = fopen($absolute, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to read file.');
            }
            fseek($handle, $offset);
            $content = fread($handle, $limit) ?: '';
            fclose($handle);
        }

        return [
            'path'    => (string) ($params['path'] ?? ''),
            'size'    => $size,
            'content' => $content === false ? '' : $content,
        ];
    }

    public function writeFile(array $params): array
    {
        $relative = (string) ($params['path'] ?? '');
        $content  = (string) ($params['content'] ?? '');
        $absolute = $this->guard->resolve($relative, true);

        if (file_put_contents($absolute, $content) === false) {
            throw new \RuntimeException('Failed to write file.');
        }

        return [
            'path'    => $relative,
            'message' => 'File written successfully.',
        ];
    }

    public function editFile(array $params): array
    {
        $relative = (string) ($params['path'] ?? '');
        $target   = (string) ($params['target'] ?? '');
        $replace  = (string) ($params['replacement'] ?? '');

        if ($target === '') {
            throw new \RuntimeException('Target text cannot be empty.');
        }

        $absolute = $this->guard->resolve($relative);
        $content  = file_get_contents($absolute);

        if ($content === false) {
            throw new \RuntimeException('Unable to read file.');
        }

        if (!str_contains($content, $target)) {
            throw new \RuntimeException('Target text not found in file.');
        }

        $updated = str_replace($target, $replace, $content, $count);

        if ($count === 0) {
            throw new \RuntimeException('No replacements were made.');
        }

        if (file_put_contents($absolute, $updated) === false) {
            throw new \RuntimeException('Failed to save edited file.');
        }

        return [
            'path'             => $relative,
            'replacements'     => $count,
            'message'          => 'File edited successfully.',
        ];
    }

    public function deleteFile(array $params): array
    {
        $relative = (string) ($params['path'] ?? '');
        $absolute = $this->guard->resolve($relative);

        if (is_dir($absolute)) {
            $items = array_diff(scandir($absolute) ?: [], ['.', '..']);
            if (!empty($items)) {
                throw new \RuntimeException('Directory is not empty.');
            }
            if (!@rmdir($absolute)) {
                throw new \RuntimeException('Failed to delete directory.');
            }
        } elseif (is_file($absolute)) {
            if (!@unlink($absolute)) {
                throw new \RuntimeException('Failed to delete file.');
            }
        } else {
            throw new \RuntimeException('Path not found.');
        }

        return ['path' => $relative, 'message' => 'Deleted successfully.'];
    }

    private function toRelative(string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', $this->guard->getRoot()), '/') . '/';
        $path = str_replace('\\', '/', $absolute);

        return ltrim(substr($path, strlen($root)), '/');
    }
}
