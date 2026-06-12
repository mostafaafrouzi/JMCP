<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\Component\Jmcp\Administrator\Service\PathGuard;

class ExtensionExecutor
{
    private PathGuard $guard;

    public function __construct(?PathGuard $guard = null)
    {
        $this->guard = $guard ?? new PathGuard();
    }

    public function installExtension(array $params): array
    {
        $path = trim((string) ($params['path'] ?? ''));
        if ($path === '') {
            throw new \RuntimeException('path to extension package (zip) is required.');
        }

        $absolute = $this->guard->resolve($path);
        if (!is_file($absolute) || !str_ends_with(strtolower($absolute), '.zip')) {
            throw new \RuntimeException('Extension package must be a .zip file.');
        }

        $installer = new Installer();
        $installer->setOverwrite(true);
        if (!$installer->install($absolute)) {
            throw new \RuntimeException('Extension install failed: ' . implode('; ', $installer->getErrors()));
        }

        return [
            'path'    => $path,
            'message' => $installer->getMessage() ?: 'Extension installed.',
            'extension' => $installer->getName(),
        ];
    }

    public function updateExtension(array $params): array
    {
        $path = trim((string) ($params['path'] ?? ''));
        $extensionId = (int) ($params['extension_id'] ?? 0);

        if ($path === '') {
            throw new \RuntimeException('path to update package (zip) is required.');
        }

        $absolute = $this->guard->resolve($path);
        if (!is_file($absolute)) {
            throw new \RuntimeException('Update package not found.');
        }

        $installer = new Installer();
        $installer->setOverwrite(true);

        if ($extensionId > 0) {
            $installer->setExtensionId($extensionId);
        }

        if (!$installer->install($absolute)) {
            throw new \RuntimeException('Extension update failed: ' . implode('; ', $installer->getErrors()));
        }

        return [
            'extension_id' => $extensionId,
            'message'      => $installer->getMessage() ?: 'Extension updated.',
        ];
    }
}
