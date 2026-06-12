<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class BannerExecutor
{
    public function createBanner(array $params): array
    {
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('name is required.');
        }

        $db = Factory::getDbo();
        $row = (object) [
            'name'       => $name,
            'alias'      => (string) ($params['alias'] ?? ''),
            'catid'      => (int) ($params['catid'] ?? 0),
            'state'      => (int) ($params['state'] ?? 1),
            'clickurl'   => (string) ($params['clickurl'] ?? ''),
            'description'=> (string) ($params['description'] ?? ''),
            'sticky'     => (int) ($params['sticky'] ?? 0),
            'language'   => (string) ($params['language'] ?? '*'),
            'created'    => Factory::getDate()->toSql(),
            'created_by' => Factory::getApplication()->getIdentity()->id ?: 0,
        ];

        $db->insertObject('#__banners', $row);

        return ['id' => (int) $db->insertid(), 'message' => 'Banner created.'];
    }

    public function updateBanner(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        if ($id <= 0 || $fields === []) {
            throw new \RuntimeException('id and fields are required.');
        }

        $fields['id'] = $id;
        $fields['modified'] = Factory::getDate()->toSql();
        Factory::getDbo()->updateObject('#__banners', (object) $fields, 'id');

        return ['id' => $id, 'message' => 'Banner updated.'];
    }

    public function deleteBanner(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('id is required.');
        }

        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)->delete('#__banners')->where('id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'Banner deleted.'];
    }

    public function createNewsfeed(array $params): array
    {
        $name = trim((string) ($params['name'] ?? ''));
        $link = trim((string) ($params['link'] ?? ''));
        if ($name === '' || $link === '') {
            throw new \RuntimeException('name and link are required.');
        }

        $db = Factory::getDbo();
        $row = (object) [
            'name'       => $name,
            'alias'      => (string) ($params['alias'] ?? ''),
            'catid'      => (int) ($params['catid'] ?? 0),
            'link'       => $link,
            'published'  => (int) ($params['published'] ?? 1),
            'numarticles'=> (int) ($params['numarticles'] ?? 5),
            'language'   => (string) ($params['language'] ?? '*'),
            'created'    => Factory::getDate()->toSql(),
            'created_by' => Factory::getApplication()->getIdentity()->id ?: 0,
        ];

        $db->insertObject('#__newsfeeds', $row);

        return ['id' => (int) $db->insertid(), 'message' => 'Newsfeed created.'];
    }

    public function updateNewsfeed(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        if ($id <= 0 || $fields === []) {
            throw new \RuntimeException('id and fields are required.');
        }

        $fields['id'] = $id;
        $fields['modified'] = Factory::getDate()->toSql();
        Factory::getDbo()->updateObject('#__newsfeeds', (object) $fields, 'id');

        return ['id' => $id, 'message' => 'Newsfeed updated.'];
    }

    public function deleteNewsfeed(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('id is required.');
        }

        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)->delete('#__newsfeeds')->where('id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'Newsfeed deleted.'];
    }
}
