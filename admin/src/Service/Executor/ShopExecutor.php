<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;

class ShopExecutor
{
    private IntegrationDetector $detector;

    public function __construct(?IntegrationDetector $detector = null)
    {
        $this->detector = $detector ?? new IntegrationDetector();
    }

    public function detectInstalledShops(array $params): array
    {
        return [
            'shops'       => $this->detector->getInstalledShops(),
            'integrations'=> $this->detector->getInstalledList(),
        ];
    }

    // --- VirtueMart ---
    public function virtuemartListProducts(array $params): array
    {
        $this->assertShop('virtuemart');
        return $this->listFromTable('#__virtuemart_products', ['virtuemart_product_id AS id', 'product_name AS name', 'published'], (int) ($params['limit'] ?? 50));
    }

    public function virtuemartGetProduct(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('*')->from('#__virtuemart_products')->where('virtuemart_product_id = ' . $id);
        $product = $db->setQuery($query)->loadAssoc();
        if (!$product) {
            throw new \RuntimeException('VirtueMart product not found.');
        }
        return $product;
    }

    public function virtuemartSaveProduct(array $params): array
    {
        $this->assertShop('virtuemart');
        $db = Factory::getDbo();
        $id = (int) ($params['id'] ?? 0);
        $data = [
            'product_name' => (string) ($params['name'] ?? ''),
            'published'    => (int) ($params['published'] ?? 1),
        ];

        if ($id > 0) {
            $data['virtuemart_product_id'] = $id;
            $row = (object) $data;
            $db->updateObject('#__virtuemart_products', $row, 'virtuemart_product_id');
        } else {
            $row = (object) $data;
            $db->insertObject('#__virtuemart_products', $row);
            $id = (int) $db->insertid();
        }

        return ['id' => $id, 'message' => 'VirtueMart product saved.'];
    }

    public function virtuemartListOrders(array $params): array
    {
        $this->assertShop('virtuemart');
        return $this->listFromTable('#__virtuemart_orders', ['virtuemart_order_id AS id', 'order_number', 'order_status', 'created_on'], (int) ($params['limit'] ?? 50));
    }

    // --- HikaShop ---
    public function hikashopListProducts(array $params): array
    {
        $this->assertShop('hikashop');
        return $this->listFromTable('#__hikashop_product', ['product_id AS id', 'product_name AS name', 'product_published AS published'], (int) ($params['limit'] ?? 50));
    }

    public function hikashopGetProduct(array $params): array
    {
        $this->assertShop('hikashop');
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $product = $db->setQuery($db->getQuery(true)->select('*')->from('#__hikashop_product')->where('product_id = ' . $id))->loadAssoc();
        if (!$product) {
            throw new \RuntimeException('HikaShop product not found.');
        }
        return $product;
    }

    public function hikashopSaveProduct(array $params): array
    {
        $this->assertShop('hikashop');
        $db = Factory::getDbo();
        $id = (int) ($params['id'] ?? 0);
        $data = [
            'product_name'       => (string) ($params['name'] ?? ''),
            'product_published'  => (int) ($params['published'] ?? 1),
            'product_description'=> (string) ($params['description'] ?? ''),
        ];

        if ($id > 0) {
            $data['product_id'] = $id;
            $row = (object) $data;
            $db->updateObject('#__hikashop_product', $row, 'product_id');
        } else {
            $row = (object) $data;
            $db->insertObject('#__hikashop_product', $row);
            $id = (int) $db->insertid();
        }

        return ['id' => $id, 'message' => 'HikaShop product saved.'];
    }

    public function hikashopListOrders(array $params): array
    {
        $this->assertShop('hikashop');
        return $this->listFromTable('#__hikashop_order', ['order_id AS id', 'order_number', 'order_status', 'order_created'], (int) ($params['limit'] ?? 50));
    }

    // --- J2Commerce ---
    public function j2commerceListProducts(array $params): array
    {
        $this->assertShop('j2commerce');
        return $this->listFromTable('#__j2commerce_products', ['id', 'title AS name', 'published'], (int) ($params['limit'] ?? 50));
    }

    public function j2commerceGetProduct(array $params): array
    {
        $this->assertShop('j2commerce');
        $id = (int) ($params['id'] ?? 0);
        $product = $this->loadFromFirstTable([
            '#__j2commerce_products',
            '#__j2_store_products',
            '#__j2commerce_product',
        ], $id);

        if ($product === null) {
            throw new \RuntimeException('J2Commerce product not found.');
        }

        return $product;
    }

    public function j2commerceSaveProduct(array $params): array
    {
        $this->assertShop('j2commerce');
        $db = Factory::getDbo();
        $id = (int) ($params['id'] ?? 0);
        $table = $this->resolveJ2CommerceProductTable();

        $data = (object) [
            'title'       => (string) ($params['name'] ?? ''),
            'published'   => (int) ($params['published'] ?? 1),
            'description' => (string) ($params['description'] ?? ''),
        ];

        if ($id > 0) {
            $data->id = $id;
            $db->updateObject($table, $data, 'id');
        } else {
            $db->insertObject($table, $data);
            $id = (int) $db->insertid();
        }

        return ['id' => $id, 'table' => $table, 'message' => 'J2Commerce product saved.'];
    }

    private function resolveJ2CommerceProductTable(): string
    {
        foreach (['#__j2commerce_products', '#__j2_store_products', '#__j2commerce_product'] as $table) {
            if ($this->tableExists($table)) {
                return $table;
            }
        }

        throw new \RuntimeException('J2Commerce product table not found on this site.');
    }

    private function assertShop(string $shop): void
    {
        if (!$this->detector->isInstalled($shop)) {
            throw new \RuntimeException(ucfirst($shop) . ' is not installed on this site.');
        }
    }

    private function listFromTable(string $table, array $columns, int $limit): array
    {
        if (!$this->tableExists($table)) {
            throw new \RuntimeException("Shop table {$table} not found. The extension may use a different schema on this site.");
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select($columns)->from($db->quoteName($table))->order('1 DESC');
        $db->setQuery($query, 0, $limit);
        return ['items' => $db->loadAssocList() ?: []];
    }

    private function tableExists(string $table): bool
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];

        return in_array($db->replacePrefix($table), $tables, true);
    }

    /** @return array<string, mixed>|null */
    private function loadFromFirstTable(array $tables, int $id): ?array
    {
        $db = Factory::getDbo();
        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $row = $db->setQuery(
                $db->getQuery(true)->select('*')->from($db->quoteName($table))->where('id = ' . $id)
            )->loadAssoc();
            if ($row) {
                $row['_table'] = $table;
                return $row;
            }
        }
        return null;
    }
}
