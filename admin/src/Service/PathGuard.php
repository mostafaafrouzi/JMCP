<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Resolves and validates filesystem paths within the Joomla root.
 */
class PathGuard
{
    private string $root;

    /** @var string[] */
    private array $blockedRelativePaths = [
        'configuration.php',
        '.env',
        'htaccess.txt',
    ];

    public function __construct(?string $root = null)
    {
        $this->root = realpath($root ?? JPATH_ROOT) ?: JPATH_ROOT;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * Resolve a relative path to an absolute path inside Joomla root.
     *
     * @throws \RuntimeException
     */
    public function resolve(string $relativePath, bool $allowCreate = false): string
    {
        $relative = $this->normaliseRelative($relativePath);

        if ($this->isBlocked($relative)) {
            throw new \RuntimeException('Access to this path is blocked for security reasons.');
        }

        $candidate = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parentDir = dirname($candidate);

        if (!is_dir($parentDir) && $allowCreate) {
            if (!@mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
                throw new \RuntimeException('Unable to create parent directory.');
            }
        }

        $realParent = realpath($parentDir);
        if ($realParent === false) {
            throw new \RuntimeException('Path does not exist.');
        }

        if (!$this->isInsideRoot($realParent)) {
            throw new \RuntimeException('Path escapes Joomla root.');
        }

        if (file_exists($candidate)) {
            $real = realpath($candidate);
            if ($real === false || !$this->isInsideRoot($real)) {
                throw new \RuntimeException('Path escapes Joomla root.');
            }

            if (is_link($real)) {
                throw new \RuntimeException('Symlink paths are not allowed.');
            }

            return $real;
        }

        if (!$allowCreate) {
            throw new \RuntimeException('File or directory not found.');
        }

        return $candidate;
    }

    private function normaliseRelative(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || $path === '.') {
            return '';
        }

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \RuntimeException('Parent directory traversal is not allowed.');
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    private function isBlocked(string $relative): bool
    {
        $lower = strtolower($relative);

        foreach ($this->blockedRelativePaths as $blocked) {
            if ($lower === strtolower($blocked)) {
                return true;
            }
        }

        return false;
    }

    private function isInsideRoot(string $absolutePath): bool
    {
        $normalisedRoot = rtrim(str_replace('\\', '/', $this->root), '/') . '/';
        $normalisedPath = str_replace('\\', '/', $absolutePath) . (is_dir($absolutePath) ? '/' : '');

        return str_starts_with($normalisedPath, $normalisedRoot);
    }
}
