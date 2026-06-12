<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Contact;

class ContactExecutor
{
    public function listContacts(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'name', 'alias', 'catid', 'published', 'email_to', 'telephone'])
            ->from('#__contact_details')
            ->order('name ASC');

        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));
        $db->setQuery($query, 0, $limit);

        return ['contacts' => $db->loadAssocList() ?: []];
    }

    public function getContact(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Contact(Factory::getDbo());
        if (!$table->load($id)) {
            throw new \RuntimeException('Contact not found.');
        }
        return $table->getProperties();
    }

    public function createContact(array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        $table = new Contact(Factory::getDbo());

        if (!$table->save([
            'name'      => $name,
            'alias'     => OutputFilter::stringURLSafe($name),
            'catid'     => (int) ($params['catid'] ?? 0),
            'published' => 1,
            'access'    => 1,
            'language'  => '*',
            'email_to'  => (string) ($params['email'] ?? ''),
            'telephone' => (string) ($params['telephone'] ?? ''),
        ])) {
            throw new \RuntimeException('Failed to create contact.');
        }

        return ['id' => (int) $table->id, 'message' => 'Contact created.'];
    }

    public function updateContact(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Contact(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException('Contact not found.');
        }

        foreach ($fields as $k => $v) {
            if (property_exists($table, $k)) {
                $table->$k = $v;
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update contact.');
        }

        return ['id' => $id, 'message' => 'Contact updated.'];
    }

    public function deleteContact(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Contact(Factory::getDbo());

        if (!$table->load($id) || !$table->delete($id)) {
            throw new \RuntimeException('Failed to delete contact.');
        }

        return ['id' => $id, 'message' => 'Contact deleted.'];
    }
}
