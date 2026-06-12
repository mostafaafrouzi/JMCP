<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Joomla-aware media path handling (com_media conventions + PathGuard).
 */
class JoomlaMediaService
{
    private PathGuard $guard;

    /** @var string[] */
    private array $allowedRoots = ['images', 'media'];

    public function __construct(?PathGuard $guard = null)
    {
        $this->guard = $guard ?? new PathGuard();
    }

    public function isMediaComponentEnabled(): bool
    {
        return ComponentHelper::isEnabled('com_media');
    }

    public function normalisePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return 'images';
        }

        foreach ($this->allowedRoots as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return $this->validateWithJoomla($path);
            }
        }

        return $this->validateWithJoomla('images/' . $path);
    }

    public function resolveAbsolute(string $relativePath, bool $allowCreate = false): string
    {
        $normalised = $this->normalisePath($relativePath);

        if ($allowCreate) {
            return $this->guard->resolve($normalised, true);
        }

        return $this->guard->resolve($normalised);
    }

    public function publicUrl(string $relativePath): string
    {
        return rtrim(Uri::root(), '/') . '/' . ltrim($this->normalisePath($relativePath), '/');
    }

    private function validateWithJoomla(string $path): string
    {
        if (class_exists(\Joomla\Component\Media\Administrator\Helper\MediaHelper::class)) {
            try {
                \Joomla\Component\Media\Administrator\Helper\MediaHelper::validatePath($path, 'local-images');
            } catch (\Throwable $e) {
                // Joomla 5 may use different adapter id; try generic validation
                if (!str_contains($path, '..')) {
                    return $path;
                }
                throw new \RuntimeException('Invalid media path: ' . $e->getMessage());
            }
        }

        if (str_contains($path, '..')) {
            throw new \RuntimeException('Invalid media path.');
        }

        return $path;
    }
}
