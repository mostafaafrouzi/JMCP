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
        $db = Factory::getDbo();
        $langTable = $this->resolveVirtuemartLangTable('products', (string) ($params['language'] ?? ''));
        $limit = max(1, min(200, (int) ($params['limit'] ?? 50)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $query = $db->getQuery(true)
            ->select([
                'p.virtuemart_product_id AS id',
                'l.product_name AS name',
                'l.product_s_desc AS short_description',
                'l.slug',
                'p.published',
            ])
            ->from($db->quoteName('#__virtuemart_products', 'p'))
            ->join('LEFT', $db->quoteName($langTable, 'l')
                . ' ON l.virtuemart_product_id = p.virtuemart_product_id')
            ->order('p.virtuemart_product_id ASC');

        if (!empty($params['category_id'])) {
            $query->join('INNER', $db->quoteName('#__virtuemart_product_categories', 'pc')
                . ' ON pc.virtuemart_product_id = p.virtuemart_product_id')
                ->where('pc.virtuemart_category_id = ' . (int) $params['category_id']);
        }

        $items = $db->setQuery($query, $offset, $limit)->loadAssocList() ?: [];

        return ['language_table' => $langTable, 'items' => $items, 'limit' => $limit, 'offset' => $offset];
    }

    public function virtuemartGetProduct(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $langTable = $this->resolveVirtuemartLangTable('products', (string) ($params['language'] ?? ''));

        $product = $db->setQuery(
            $db->getQuery(true)->select('p.*')->from($db->quoteName('#__virtuemart_products', 'p'))
                ->where('p.virtuemart_product_id = ' . $id)
        )->loadAssoc();

        if (!$product) {
            throw new \RuntimeException('VirtueMart product not found.');
        }

        $lang = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName($langTable))
                ->where('virtuemart_product_id = ' . $id)
        )->loadAssoc() ?: [];

        $categories = $db->setQuery(
            $db->getQuery(true)->select('virtuemart_category_id')->from('#__virtuemart_product_categories')
                ->where('virtuemart_product_id = ' . $id)
        )->loadColumn() ?: [];

        return ['product' => $product, 'language' => $lang, 'language_table' => $langTable, 'category_ids' => array_map('intval', $categories)];
    }

    public function virtuemartSaveProduct(array $params): array
    {
        return $this->virtuemartUpdateProduct($params);
    }

    public function virtuemartUpdateProduct(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $langTable = $this->resolveVirtuemartLangTable('products', (string) ($params['language'] ?? ''));
        $db = Factory::getDbo();

        $langFields = [];
        foreach ([
            'name' => 'product_name',
            'short_description' => 'product_s_desc',
            'description' => 'product_desc',
            'slug' => 'slug',
            'metadesc' => 'metadesc',
            'metakey' => 'metakey',
            'customtitle' => 'customtitle',
        ] as $param => $col) {
            if (array_key_exists($param, $params)) {
                $langFields[$col] = (string) $params[$param];
            }
        }

        $coreFields = [];
        if (isset($params['published'])) {
            $coreFields['published'] = (int) $params['published'];
        }

        if ($id <= 0 && empty($langFields['product_name'])) {
            throw new \RuntimeException('id or name is required.');
        }

        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'language_table' => $langTable, 'language_fields' => array_keys($langFields), 'core_fields' => array_keys($coreFields)];
        }

        if ($id <= 0) {
            $core = (object) ['published' => (int) ($params['published'] ?? 1)];
            $db->insertObject('#__virtuemart_products', $core, 'virtuemart_product_id');
            $id = (int) ($core->virtuemart_product_id ?? $db->insertid());
            $langFields['virtuemart_product_id'] = $id;
            $db->insertObject($langTable, (object) $langFields);
        } else {
            if ($coreFields !== []) {
                $coreFields['virtuemart_product_id'] = $id;
                $coreRow = (object) $coreFields;
                $db->updateObject('#__virtuemart_products', $coreRow, 'virtuemart_product_id');
            }
            if ($langFields !== []) {
                $exists = (int) $db->setQuery(
                    $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($langTable))
                        ->where('virtuemart_product_id = ' . $id)
                )->loadResult();
                $langFields['virtuemart_product_id'] = $id;
                if ($exists > 0) {
                    $langRow = (object) $langFields;
                    $db->updateObject($langTable, $langRow, 'virtuemart_product_id');
                } else {
                    $langRow = (object) $langFields;
                    $db->insertObject($langTable, $langRow);
                }
            }
        }

        return ['id' => $id, 'language_table' => $langTable, 'message' => 'VirtueMart product updated.'];
    }

    public function virtuemartUpdateCategory(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Category id is required.');
        }

        $dryRun = (bool) ($params['dry_run'] ?? false);
        $langTable = $this->resolveVirtuemartLangTable('categories', (string) ($params['language'] ?? ''));
        $db = Factory::getDbo();

        $langFields = [];
        foreach ([
            'name' => 'category_name',
            'description' => 'category_description',
            'slug' => 'slug',
            'metadesc' => 'metadesc',
            'metakey' => 'metakey',
            'customtitle' => 'customtitle',
        ] as $param => $col) {
            if (array_key_exists($param, $params)) {
                $langFields[$col] = (string) $params[$param];
            }
        }

        $coreFields = [];
        if (isset($params['published'])) {
            $coreFields['published'] = (int) $params['published'];
        }
        if (isset($params['ordering'])) {
            $coreFields['ordering'] = (int) $params['ordering'];
        }

        if ($langFields === [] && $coreFields === []) {
            throw new \RuntimeException('No fields to update.');
        }

        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'language_table' => $langTable];
        }

        if ($coreFields !== []) {
            $coreFields['virtuemart_category_id'] = $id;
            $coreRow = (object) $coreFields;
            $db->updateObject('#__virtuemart_categories', $coreRow, 'virtuemart_category_id');
        }

        if ($langFields !== []) {
            $exists = (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($langTable))
                    ->where('virtuemart_category_id = ' . $id)
            )->loadResult();
            $langFields['virtuemart_category_id'] = $id;
            if ($exists > 0) {
                $langRow = (object) $langFields;
                $db->updateObject($langTable, $langRow, 'virtuemart_category_id');
            } else {
                $langRow = (object) $langFields;
                $db->insertObject($langTable, $langRow);
            }
        }

        return ['id' => $id, 'language_table' => $langTable, 'message' => 'VirtueMart category updated.'];
    }

    public function virtuemartUpdateVendor(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 1);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $langTable = $this->resolveVirtuemartLangTable('vendors', (string) ($params['language'] ?? ''));
        $db = Factory::getDbo();

        $langFields = [];
        foreach ([
            'name' => 'vendor_store_name',
            'store_name' => 'vendor_store_name',
            'store_description' => 'vendor_store_desc',
            'phone' => 'vendor_phone',
            'slug' => 'slug',
            'customtitle' => 'customtitle',
            'metadesc' => 'metadesc',
            'metakey' => 'metakey',
        ] as $param => $col) {
            if (array_key_exists($param, $params)) {
                $langFields[$col] = (string) $params[$param];
            }
        }

        if ($langFields === []) {
            throw new \RuntimeException('No vendor fields to update.');
        }

        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'language_table' => $langTable];
        }

        $exists = (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($langTable))
                ->where('virtuemart_vendor_id = ' . $id)
        )->loadResult();
        $langFields['virtuemart_vendor_id'] = $id;
        $langRow = (object) $langFields;
        if ($exists > 0) {
            $db->updateObject($langTable, $langRow, 'virtuemart_vendor_id');
        } else {
            $db->insertObject($langTable, $langRow);
        }

        return ['id' => $id, 'language_table' => $langTable, 'message' => 'VirtueMart vendor updated.'];
    }

    public function virtuemartCloneLanguageTables(array $params): array
    {
        $this->assertShop('virtuemart');
        $source = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($params['source_suffix'] ?? 'en_gb')));
        $target = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($params['target_suffix'] ?? 'fa_ir')));
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($source === '' || $target === '' || $source === $target) {
            throw new \RuntimeException('Valid source_suffix and target_suffix are required.');
        }

        $db = Factory::getDbo();
        $prefix = $db->getPrefix();
        $cloned = [];
        $tables = $db->getTableList() ?: [];

        foreach ($tables as $table) {
            if (!str_starts_with($table, $prefix . 'virtuemart_') || !str_ends_with($table, '_' . $source)) {
                continue;
            }
            $dst = preg_replace('/_' . preg_quote($source, '/') . '$/', '_' . $target, $table);
            if ($dst === null || $dst === $table) {
                continue;
            }
            if ($dryRun) {
                $cloned[] = ['source' => $table, 'target' => $dst, 'dry_run' => true];
                continue;
            }
            $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName($dst) . ' LIKE ' . $db->quoteName($table))->execute();
            $db->setQuery('TRUNCATE ' . $db->quoteName($dst))->execute();
            $db->setQuery('INSERT INTO ' . $db->quoteName($dst) . ' SELECT * FROM ' . $db->quoteName($table))->execute();
            $cloned[] = ['source' => $table, 'target' => $dst, 'cloned' => true];
        }

        return [
            'source_suffix' => $source,
            'target_suffix' => $target,
            'tables'        => $cloned,
            'message'       => $dryRun ? 'Dry run: language tables identified.' : 'VirtueMart language tables cloned.',
        ];
    }

    public function virtuemartSetProductPrice(array $params): array
    {
        $this->assertShop('virtuemart');
        $productId = (int) ($params['product_id'] ?? 0);
        $price = $params['price'] ?? null;
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($productId <= 0 || $price === null) {
            throw new \RuntimeException('product_id and price are required.');
        }

        $db = Factory::getDbo();
        $priceId = (int) ($params['price_id'] ?? 0);
        $row = [
            'virtuemart_product_id' => $productId,
            'product_price'         => (float) $price,
            'product_currency'      => (int) ($params['currency'] ?? 0) ?: null,
            'override'              => (int) ($params['override'] ?? 0),
            'modified_on'           => Factory::getDate()->toSql(),
        ];

        if ($dryRun) {
            return ['product_id' => $productId, 'dry_run' => true, 'price' => (float) $price];
        }

        if ($priceId > 0) {
            $row['virtuemart_product_price_id'] = $priceId;
            $db->updateObject('#__virtuemart_product_prices', (object) $row, 'virtuemart_product_price_id');
        } else {
            $existing = (int) $db->setQuery(
                $db->getQuery(true)->select('virtuemart_product_price_id')
                    ->from('#__virtuemart_product_prices')
                    ->where('virtuemart_product_id = ' . $productId)
                    ->order('virtuemart_product_price_id ASC')
            )->loadResult();

            if ($existing > 0) {
                $row['virtuemart_product_price_id'] = $existing;
                $db->updateObject('#__virtuemart_product_prices', (object) $row, 'virtuemart_product_price_id');
                $priceId = $existing;
            } else {
                $row['created_on'] = Factory::getDate()->toSql();
                $db->insertObject('#__virtuemart_product_prices', (object) $row);
                $priceId = (int) $db->insertid();
            }
        }

        return ['product_id' => $productId, 'price_id' => $priceId, 'price' => (float) $price, 'message' => 'Product price updated.'];
    }

    public function virtuemartAssignProductCategories(array $params): array
    {
        $this->assertShop('virtuemart');
        $productId = (int) ($params['product_id'] ?? 0);
        $categoryIds = array_map('intval', (array) ($params['category_ids'] ?? []));
        $mode = (string) ($params['mode'] ?? 'replace');
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($productId <= 0) {
            throw new \RuntimeException('product_id is required.');
        }

        $db = Factory::getDbo();
        if ($mode === 'replace' && !$dryRun) {
            $db->setQuery(
                $db->getQuery(true)->delete('#__virtuemart_product_categories')
                    ->where('virtuemart_product_id = ' . $productId)
            )->execute();
        }

        $assigned = [];
        foreach ($categoryIds as $i => $catId) {
            if ($catId <= 0) {
                continue;
            }
            if ($dryRun) {
                $assigned[] = ['category_id' => $catId, 'dry_run' => true];
                continue;
            }
            if ($mode === 'add') {
                $exists = (int) $db->setQuery(
                    $db->getQuery(true)->select('COUNT(*)')->from('#__virtuemart_product_categories')
                        ->where('virtuemart_product_id = ' . $productId)
                        ->where('virtuemart_category_id = ' . $catId)
                )->loadResult();
                if ($exists > 0) {
                    continue;
                }
            }
            $db->insertObject('#__virtuemart_product_categories', (object) [
                'virtuemart_product_id'  => $productId,
                'virtuemart_category_id' => $catId,
                'ordering'               => $i,
            ]);
            $assigned[] = ['category_id' => $catId];
        }

        return ['product_id' => $productId, 'categories' => $assigned, 'message' => 'Product categories assigned.'];
    }

    public function virtuemartManageProductMedia(array $params): array
    {
        $this->assertShop('virtuemart');
        $action = strtolower((string) ($params['action'] ?? 'list'));
        $productId = (int) ($params['product_id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($productId <= 0) {
            throw new \RuntimeException('product_id is required.');
        }

        $db = Factory::getDbo();

        if ($action === 'list') {
            $query = $db->getQuery(true)
                ->select(['pm.id', 'pm.virtuemart_media_id AS media_id', 'pm.ordering', 'm.file_url', 'm.file_title', 'm.file_description'])
                ->from($db->quoteName('#__virtuemart_product_medias', 'pm'))
                ->join('LEFT', $db->quoteName('#__virtuemart_medias', 'm') . ' ON m.virtuemart_media_id = pm.virtuemart_media_id')
                ->where('pm.virtuemart_product_id = ' . $productId)
                ->order('pm.ordering ASC');
            return ['product_id' => $productId, 'media' => $db->setQuery($query)->loadAssocList() ?: []];
        }

        if ($action === 'attach') {
            $mediaId = (int) ($params['media_id'] ?? 0);
            if ($mediaId <= 0) {
                throw new \RuntimeException('media_id is required for attach.');
            }
            if ($dryRun) {
                return ['product_id' => $productId, 'media_id' => $mediaId, 'dry_run' => true];
            }
            $db->insertObject('#__virtuemart_product_medias', (object) [
                'virtuemart_product_id' => $productId,
                'virtuemart_media_id'   => $mediaId,
                'ordering'              => (int) ($params['ordering'] ?? 0),
            ]);
            return ['product_id' => $productId, 'media_id' => $mediaId, 'message' => 'Media attached.'];
        }

        if ($action === 'detach') {
            $linkId = (int) ($params['link_id'] ?? 0);
            $mediaId = (int) ($params['media_id'] ?? 0);
            if ($linkId <= 0 && $mediaId <= 0) {
                throw new \RuntimeException('link_id or media_id required for detach.');
            }
            if ($dryRun) {
                return ['product_id' => $productId, 'dry_run' => true];
            }
            $query = $db->getQuery(true)->delete('#__virtuemart_product_medias')
                ->where('virtuemart_product_id = ' . $productId);
            if ($linkId > 0) {
                $query->where('id = ' . $linkId);
            } else {
                $query->where('virtuemart_media_id = ' . $mediaId);
            }
            $db->setQuery($query)->execute();
            return ['product_id' => $productId, 'message' => 'Media detached.'];
        }

        throw new \RuntimeException('Invalid action. Use list, attach, or detach.');
    }

    public function virtuemartGetConfig(array $params): array
    {
        $this->assertShop('virtuemart');
        $db = Factory::getDbo();
        $raw = (string) $db->setQuery(
            $db->getQuery(true)->select('config')->from('#__virtuemart_configs')->where('virtuemart_config_id = 1')
        )->loadResult();

        return ['config' => $this->parseVmConfig($raw), 'raw' => $raw];
    }

    public function virtuemartSetConfig(array $params): array
    {
        $this->assertShop('virtuemart');
        $updates = (array) ($params['config'] ?? []);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($updates === []) {
            throw new \RuntimeException('config object with key/value pairs is required.');
        }

        $current = $this->virtuemartGetConfig([]);
        $merged = array_merge($current['config'], $updates);
        $raw = $this->buildVmConfig($merged);

        if ($dryRun) {
            return ['dry_run' => true, 'config' => $merged];
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)->update('#__virtuemart_configs')
                ->set('config = ' . $db->quote($raw))
                ->where('virtuemart_config_id = 1')
        )->execute();

        return ['config' => $merged, 'message' => 'VirtueMart configuration updated.'];
    }

    public function virtuemartListCustomFields(array $params): array
    {
        $this->assertShop('virtuemart');
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));
        $query = $db->getQuery(true)
            ->select([
                'virtuemart_custom_id AS id', 'custom_title AS title', 'custom_element AS element',
                'field_type', 'is_cart_attribute', 'published', 'ordering',
            ])
            ->from('#__virtuemart_customs')
            ->order('ordering ASC');

        if (isset($params['published'])) {
            $query->where('published = ' . (int) $params['published']);
        }

        return ['custom_fields' => $db->setQuery($query, 0, $limit)->loadAssocList() ?: []];
    }

    public function virtuemartSetCustomField(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $db = Factory::getDbo();

        $data = [];
        foreach (['custom_title' => 'title', 'custom_element' => 'element', 'field_type' => 'field_type', 'published' => 'published', 'ordering' => 'ordering', 'custom_value' => 'default_value', 'custom_desc' => 'description'] as $col => $param) {
            if (isset($params[$param])) {
                $data[$col] = $params[$param];
            }
        }

        if ($data === []) {
            throw new \RuntimeException('At least one field to update is required.');
        }

        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'fields' => array_keys($data)];
        }

        if ($id > 0) {
            $data['virtuemart_custom_id'] = $id;
            $data['modified_on'] = Factory::getDate()->toSql();
            $db->updateObject('#__virtuemart_customs', (object) $data, 'virtuemart_custom_id');
        } else {
            $data['custom_title'] = (string) ($data['custom_title'] ?? $params['title'] ?? 'Custom Field');
            $data['custom_element'] = (string) ($data['custom_element'] ?? $params['element'] ?? 'custom_' . time());
            $data['field_type'] = (string) ($data['field_type'] ?? $params['field_type'] ?? 'S');
            $data['published'] = (int) ($data['published'] ?? 1);
            $data['created_on'] = Factory::getDate()->toSql();
            $db->insertObject('#__virtuemart_customs', (object) $data);
            $id = (int) $db->insertid();
        }

        return ['id' => $id, 'message' => 'VirtueMart custom field saved.'];
    }

    /** @return array<string, string> */
    private function parseVmConfig(string $raw): array
    {
        $out = [];
        foreach (explode('|', $raw) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $val] = explode('=', $part, 2);
            $out[trim($key)] = trim($val, '"');
        }
        return $out;
    }

    /** @param array<string, mixed> $config */
    private function buildVmConfig(array $config): string
    {
        $parts = [];
        foreach ($config as $key => $value) {
            $parts[] = $key . '="' . str_replace('"', '\\"', (string) $value) . '"';
        }
        return implode('|', $parts);
    }

    public function virtuemartListOrders(array $params): array
    {
        $this->assertShop('virtuemart');
        return $this->listFromTable('#__virtuemart_orders', ['virtuemart_order_id AS id', 'order_number', 'order_status', 'created_on'], (int) ($params['limit'] ?? 50));
    }

    public function virtuemartListCategories(array $params): array
    {
        $this->assertShop('virtuemart');
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));
        $langTable = $this->resolveVirtuemartLangTable('categories', (string) ($params['language'] ?? ''));

        $query = $db->getQuery(true)
            ->select([
                'c.virtuemart_category_id AS id',
                'c.category_parent_id AS parent_id',
                'c.published',
                'c.ordering',
                'l.category_name AS name',
                'l.category_description AS description',
                'l.slug',
            ])
            ->from($db->quoteName('#__virtuemart_categories', 'c'))
            ->join('LEFT', $db->quoteName($langTable, 'l')
                . ' ON l.virtuemart_category_id = c.virtuemart_category_id')
            ->order('c.ordering ASC');

        return [
            'language_table' => $langTable,
            'categories'     => $db->setQuery($query, 0, $limit)->loadAssocList() ?: [],
        ];
    }

    public function virtuemartGetOrder(array $params): array
    {
        $this->assertShop('virtuemart');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Order id is required.');
        }

        $db = Factory::getDbo();
        $order = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__virtuemart_orders')->where('virtuemart_order_id = ' . $id)
        )->loadAssoc();

        if (!$order) {
            throw new \RuntimeException('VirtueMart order not found.');
        }

        $items = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__virtuemart_order_items')->where('virtuemart_order_id = ' . $id)
        )->loadAssocList() ?: [];

        $userinfo = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__virtuemart_order_userinfos')->where('virtuemart_order_id = ' . $id)
        )->loadAssocList() ?: [];

        return ['order' => $order, 'items' => $items, 'userinfo' => $userinfo];
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

    private function resolveVirtuemartLangTable(string $entity, string $language): string
    {
        $suffix = $this->normaliseLangSuffix($language);
        $candidates = [$suffix];

        if ($suffix !== 'en_gb') {
            $candidates[] = 'en_gb';
        }

        foreach ($candidates as $candidate) {
            $table = '#__virtuemart_' . $entity . '_' . $candidate;
            if ($this->tableExists($table)) {
                return $table;
            }
        }

        throw new \RuntimeException('VirtueMart language table not found for ' . $entity);
    }

    private function normaliseLangSuffix(string $language): string
    {
        if ($language !== '') {
            return strtolower(str_replace('-', '_', $language));
        }

        $lang = (string) Factory::getApplication()->getLanguage()->getTag();

        return strtolower(str_replace('-', '_', $lang));
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
