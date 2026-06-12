<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserHelper;

class UserExecutor
{
    public function listUsers(array $params): array
    {
        $db = Factory::getDbo();
        $search = trim((string) ($params['search'] ?? ''));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 25)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $query = $db->getQuery(true)
            ->select(['u.id', 'u.name', 'u.username', 'u.email', 'u.block', 'u.registerDate', 'u.lastvisitDate'])
            ->from($db->quoteName('#__users', 'u'))
            ->order('u.id DESC');

        if ($search !== '') {
            $like = '%' . $db->escape($search, true) . '%';
            $query->where('(' . $db->quoteName('u.name') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('u.username') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('u.email') . ' LIKE ' . $db->quote($like) . ')');
        }

        if (isset($params['block'])) {
            $query->where($db->quoteName('u.block') . ' = ' . (int) $params['block']);
        }

        $users = $db->setQuery($query, $offset, $limit)->loadAssocList() ?: [];

        return ['users' => $users, 'limit' => $limit, 'offset' => $offset];
    }

    public function getUser(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('User id is required.');
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'name', 'username', 'email', 'block', 'registerDate', 'lastvisitDate', 'params'])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = ' . $id);

        $user = $db->setQuery($query)->loadAssoc();
        if (!$user) {
            throw new \RuntimeException(sprintf('User %d not found.', $id));
        }

        $groupsQuery = $db->getQuery(true)
            ->select(['g.id', 'g.title'])
            ->from($db->quoteName('#__usergroups', 'g'))
            ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm') . ' ON m.group_id = g.id')
            ->where('m.user_id = ' . $id);

        $user['groups'] = $db->setQuery($groupsQuery)->loadAssocList() ?: [];

        return $user;
    }

    public function listUserGroups(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'parent_id', 'title', 'lft', 'rgt'])
            ->from($db->quoteName('#__usergroups'))
            ->order('lft ASC');

        return ['groups' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function createUser(array $params): array
    {
        $name = trim((string) ($params['name'] ?? ''));
        $username = trim((string) ($params['username'] ?? ''));
        $email = trim((string) ($params['email'] ?? ''));
        $password = (string) ($params['password'] ?? '');

        if ($name === '' || $username === '' || $email === '') {
            throw new \RuntimeException('name, username, and email are required.');
        }

        if ($password === '') {
            $password = UserHelper::genRandomPassword(16);
        }

        $db = Factory::getDbo();
        $row = (object) [
            'name'          => $name,
            'username'      => $username,
            'email'         => $email,
            'password'      => UserHelper::hashPassword($password),
            'block'         => (int) ($params['block'] ?? 0),
            'sendEmail'     => 0,
            'registerDate'  => Factory::getDate()->toSql(),
            'params'        => '{}',
        ];

        $db->insertObject('#__users', $row);
        $userId = (int) $db->insertid();

        $groupIds = array_map('intval', (array) ($params['group_ids'] ?? [2]));
        foreach ($groupIds as $gid) {
            if ($gid > 0) {
                $db->insertObject('#__user_usergroup_map', (object) ['user_id' => $userId, 'group_id' => $gid]);
            }
        }

        return [
            'id'       => $userId,
            'username' => $username,
            'message'  => 'User created.',
        ];
    }

    public function updateUser(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);

        if ($id <= 0 || $fields === []) {
            throw new \RuntimeException('id and fields are required.');
        }

        if (isset($fields['password']) && $fields['password'] !== '') {
            $fields['password'] = UserHelper::hashPassword((string) $fields['password']);
        } else {
            unset($fields['password']);
        }

        $fields['id'] = $id;
        Factory::getDbo()->updateObject('#__users', (object) $fields, 'id');

        return ['id' => $id, 'message' => 'User updated.'];
    }

    public function assignUserGroups(array $params): array
    {
        $userId = (int) ($params['user_id'] ?? 0);
        $groupIds = array_map('intval', (array) ($params['group_ids'] ?? []));
        $mode = (string) ($params['mode'] ?? 'replace');

        if ($userId <= 0) {
            throw new \RuntimeException('user_id is required.');
        }

        $db = Factory::getDbo();
        if ($mode === 'replace') {
            $db->setQuery(
                $db->getQuery(true)->delete('#__user_usergroup_map')->where('user_id = ' . $userId)
            )->execute();
        }

        $assigned = [];
        foreach ($groupIds as $gid) {
            if ($gid <= 0) {
                continue;
            }
            if ($mode === 'add') {
                $exists = (int) $db->setQuery(
                    $db->getQuery(true)->select('COUNT(*)')->from('#__user_usergroup_map')
                        ->where('user_id = ' . $userId)->where('group_id = ' . $gid)
                )->loadResult();
                if ($exists > 0) {
                    continue;
                }
            }
            $db->insertObject('#__user_usergroup_map', (object) ['user_id' => $userId, 'group_id' => $gid]);
            $assigned[] = $gid;
        }

        return ['user_id' => $userId, 'group_ids' => $assigned, 'message' => 'User groups assigned.'];
    }
}
