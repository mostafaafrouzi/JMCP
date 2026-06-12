<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\JoomlaMediaService;

class MediaExecutor
{
    private JoomlaMediaService $media;

    public function __construct(?JoomlaMediaService $media = null)
    {
        $this->media = $media ?? new JoomlaMediaService();
    }

    public function listMedia(array $params): array
    {
        $path = $this->media->normalisePath((string) ($params['path'] ?? 'images'));
        $absolute = $this->media->resolveAbsolute($path);

        if (!is_dir($absolute)) {
            throw new \RuntimeException('Media path not found: ' . $path);
        }

        $items = [];
        foreach (scandir($absolute) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $absolute . '/' . $entry;
            $items[] = [
                'name'  => $entry,
                'type'  => is_dir($full) ? 'folder' : 'file',
                'size'  => is_file($full) ? filesize($full) : null,
                'url'   => is_file($full) ? $this->media->publicUrl($path . '/' . $entry) : null,
            ];
        }

        return [
            'path'              => $path,
            'com_media_enabled' => $this->media->isMediaComponentEnabled(),
            'items'             => $items,
        ];
    }

    public function getMedia(array $params): array
    {
        $path = $this->media->normalisePath((string) ($params['path'] ?? ''));
        $absolute = $this->media->resolveAbsolute($path);

        if (!is_file($absolute)) {
            throw new \RuntimeException('Media file not found: ' . $path);
        }

        $content = file_get_contents($absolute);
        if ($content === false) {
            throw new \RuntimeException('Failed to read media file.');
        }

        return [
            'path'              => $path,
            'url'               => $this->media->publicUrl($path),
            'size'              => filesize($absolute),
            'mime'              => mime_content_type($absolute) ?: 'application/octet-stream',
            'content_base64'    => base64_encode($content),
            'com_media_enabled' => $this->media->isMediaComponentEnabled(),
        ];
    }

    public function updateMedia(array $params): array
    {
        return $this->uploadMedia($params);
    }

    public function uploadMedia(array $params): array
    {
        $path = $this->media->normalisePath((string) ($params['path'] ?? 'images'));
        $content = (string) ($params['content'] ?? '');
        $filename = basename((string) ($params['filename'] ?? 'upload.bin'));

        if ($filename === '' || str_contains($filename, '..')) {
            throw new \RuntimeException('Invalid filename.');
        }

        $dir = $this->media->resolveAbsolute($path, true);
        $target = $dir . '/' . $filename;

        if (is_string($params['content_base64'] ?? null)) {
            $decoded = base64_decode((string) $params['content_base64'], true);
            if ($decoded === false) {
                throw new \RuntimeException('Invalid base64 content.');
            }
            $content = $decoded;
        }

        if ($content === '') {
            throw new \RuntimeException('No file content provided.');
        }

        if (file_put_contents($target, $content) === false) {
            throw new \RuntimeException('Failed to write media file.');
        }

        return [
            'path'    => $path . '/' . $filename,
            'url'     => $this->media->publicUrl($path . '/' . $filename),
            'message' => 'File uploaded successfully.',
        ];
    }

    public function deleteMedia(array $params): array
    {
        $path = $this->media->normalisePath((string) ($params['path'] ?? ''));
        $absolute = $this->media->resolveAbsolute($path);

        if (!is_file($absolute)) {
            throw new \RuntimeException('Media file not found.');
        }

        if (!unlink($absolute)) {
            throw new \RuntimeException('Failed to delete media file.');
        }

        return ['path' => $path, 'message' => 'Media file deleted.'];
    }

    public function createMediaFolder(array $params): array
    {
        $path = $this->media->normalisePath((string) ($params['path'] ?? 'images/new-folder'));
        $absolute = $this->media->resolveAbsolute($path, true);

        if (!is_dir($absolute) && !mkdir($absolute, 0755, true)) {
            throw new \RuntimeException('Failed to create media folder.');
        }

        return ['path' => $path, 'message' => 'Folder created.'];
    }
}
