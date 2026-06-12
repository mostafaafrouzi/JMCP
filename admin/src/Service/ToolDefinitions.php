<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Single source of truth for all JMCP tool schemas.
 */
class ToolDefinitions
{
    /** @return array<int, array<string, mixed>> */
    public static function getAll(): array
    {
        return array_merge(
            self::core(),
            self::phase1(),
            self::phase2(),
            self::phase3(),
            self::phase4()
        );
    }

    /** @return array<int, array<string, mixed>> */
    private static function core(): array
    {
        return [
            self::t('get_site_info', 'Get Joomla site metadata. Call first.', ['type' => 'object'], 'read'),
            self::t('discover_tools', 'Discover all tools, integrations, skills and instructions.', ['type' => 'object'], 'read'),
            self::t('list_extensions', 'List installed extensions.', ['type' => 'object', 'properties' => ['type' => ['type' => 'string']]], 'read'),
            self::t('list_template_styles', 'List template styles.', ['type' => 'object'], 'read'),
            self::t('list_tags', 'List content tags.', ['type' => 'object'], 'read'),
            self::t('get_component_params', 'Read component config.', ['type' => 'object', 'properties' => ['option' => ['type' => 'string']], 'required' => ['option']], 'read'),
            self::t('list_articles', 'List/search articles.', ['type' => 'object', 'properties' => ['search' => ['type' => 'string'], 'catid' => ['type' => 'integer'], 'state' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']]], 'read'),
            self::t('get_article', 'Get article by ID (raw DB content by default).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'raw_content' => ['type' => 'boolean']], 'required' => ['id']], 'read'),
            self::t('create_article', 'Create article.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'catid' => ['type' => 'integer'], 'introtext' => ['type' => 'string'], 'fulltext' => ['type' => 'string'], 'state' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['title', 'catid', 'introtext']], 'write'),
            self::t('update_article', 'Update article.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_article', 'Delete article (trash default; force requires trash state + expected_title).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean'], 'expected_title' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id']], 'destructive'),
            self::t('list_categories', 'List categories.', ['type' => 'object'], 'read'),
            self::t('get_category', 'Get category.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_category', 'Create category.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'extension' => ['type' => 'string'], 'parent_id' => ['type' => 'integer']], 'required' => ['title']], 'write'),
            self::t('update_category', 'Update category.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_category', 'Delete category.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('list_menus', 'List menu types.', ['type' => 'object'], 'read'),
            self::t('list_menu_items', 'List menu items.', ['type' => 'object'], 'read'),
            self::t('get_menu_item', 'Get menu item.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_menu_item', 'Create menu item.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'menutype' => ['type' => 'string'], 'type' => ['type' => 'string'], 'link' => ['type' => 'string']], 'required' => ['title', 'menutype', 'link']], 'write'),
            self::t('update_menu_item', 'Update menu item.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_menu_item', 'Delete menu item.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('list_modules', 'List modules.', ['type' => 'object'], 'read'),
            self::t('get_module', 'Get module.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_module', 'Create module.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'module' => ['type' => 'string'], 'position' => ['type' => 'string']], 'required' => ['title', 'module', 'position']], 'write'),
            self::t('update_module', 'Update module.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_module', 'Delete module.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('list_plugins', 'List plugins.', ['type' => 'object'], 'read'),
            self::t('toggle_plugin_state', 'Enable/disable plugin.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'enabled' => ['type' => 'boolean']], 'required' => ['id', 'enabled']], 'write'),
            self::t('update_plugin_params', 'Update plugin params.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'params' => ['type' => 'object']], 'required' => ['id', 'params']], 'write'),
            self::t('list_directory', 'List directory.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']]], 'read'),
            self::t('read_file', 'Read file.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'read'),
            self::t('write_file', 'Write file.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['path', 'content']], 'write'),
            self::t('edit_file', 'Edit file search/replace.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'target' => ['type' => 'string'], 'replacement' => ['type' => 'string']], 'required' => ['path', 'target', 'replacement']], 'write'),
            self::t('delete_file', 'Delete file.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'destructive'),
            self::t('list_db_tables', 'List DB tables.', ['type' => 'object'], 'read'),
            self::t('get_db_table_columns', 'Get table columns.', ['type' => 'object', 'properties' => ['table' => ['type' => 'string']], 'required' => ['table']], 'read'),
            self::t('execute_sql', 'Execute SQL.', ['type' => 'object', 'properties' => ['sql' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['sql']], 'execute'),
            self::t('list_sp_pages', 'List SP Page Builder pages.', ['type' => 'object'], 'read'),
            self::t('get_sp_page', 'Get SP page.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('save_sp_page', 'Save SP page.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'layout' => ['type' => 'string']], 'required' => ['title', 'layout']], 'write'),
            self::t('execute_php', 'Execute PHP in Joomla context.', ['type' => 'object', 'properties' => ['code' => ['type' => 'string']], 'required' => ['code']], 'execute'),
            self::t('run_cli_command', 'Run Joomla CLI command.', ['type' => 'object', 'properties' => ['command' => ['type' => 'string']], 'required' => ['command']], 'execute'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase1(): array
    {
        return [
            self::t('list_media', 'List media files/folders.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']]], 'read'),
            self::t('get_media', 'Get media file info and base64.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'read'),
            self::t('upload_media', 'Upload media (base64).', ['type' => 'object', 'properties' => ['folder' => ['type' => 'string'], 'path' => ['type' => 'string'], 'content_base64' => ['type' => 'string']], 'required' => ['content_base64']], 'write'),
            self::t('create_media_folder', 'Create media folder.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'write'),
            self::t('update_media', 'Update/overwrite media file.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content_base64' => ['type' => 'string']], 'required' => ['path', 'content_base64']], 'write'),
            self::t('delete_media', 'Delete media file/folder.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'destructive'),
            self::t('list_article_versions', 'List article version history.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('get_article_version', 'Get article version.', ['type' => 'object', 'properties' => ['version_id' => ['type' => 'integer']], 'required' => ['version_id']], 'read'),
            self::t('restore_article_version', 'Restore article version.', ['type' => 'object', 'properties' => ['version_id' => ['type' => 'integer']], 'required' => ['version_id']], 'write'),
            self::t('delete_article_version', 'Delete article version.', ['type' => 'object', 'properties' => ['version_id' => ['type' => 'integer']], 'required' => ['version_id']], 'destructive'),
            self::t('keep_article_version', 'Mark version keep forever.', ['type' => 'object', 'properties' => ['version_id' => ['type' => 'integer']], 'required' => ['version_id']], 'write'),
            self::t('list_content_languages', 'List content languages.', ['type' => 'object'], 'read'),
            self::t('get_content_language', 'Get language.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_content_language', 'Create language.', ['type' => 'object', 'properties' => ['lang_code' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['lang_code', 'title']], 'write'),
            self::t('update_content_language', 'Update language.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('list_article_associations', 'List article translations.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('set_article_associations', 'Set article associations.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'associations' => ['type' => 'object']], 'required' => ['id', 'associations']], 'write'),
            self::t('list_menu_item_associations', 'List menu item associations.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('set_menu_item_associations', 'Set menu item associations.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'associations' => ['type' => 'object']], 'required' => ['id', 'associations']], 'write'),
            self::t('list_installed_templates', 'List installed templates.', ['type' => 'object'], 'read'),
            self::t('get_template_style', 'Get template style.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_template_style', 'Create template style.', ['type' => 'object', 'properties' => ['template' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['template', 'title']], 'write'),
            self::t('update_template_style', 'Update template style.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_template_style', 'Delete template style.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('create_template_override', 'Create template override file.', ['type' => 'object', 'properties' => ['component' => ['type' => 'string'], 'view' => ['type' => 'string'], 'layout' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['component', 'view']], 'write'),
            self::t('create_tag', 'Create tag.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title']], 'write'),
            self::t('update_tag', 'Update tag.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_tag', 'Delete tag.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('list_custom_fields', 'List custom fields.', ['type' => 'object'], 'read'),
            self::t('get_custom_field', 'Get custom field.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_custom_field', 'Create custom field.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'name' => ['type' => 'string'], 'type' => ['type' => 'string']], 'required' => ['title', 'name']], 'write'),
            self::t('update_field_values', 'Update custom field values for item.', ['type' => 'object', 'properties' => ['item_id' => ['type' => 'integer'], 'values' => ['type' => 'object']], 'required' => ['item_id', 'values']], 'write'),
            self::t('list_contacts', 'List contacts.', ['type' => 'object'], 'read'),
            self::t('get_contact', 'Get contact.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('create_contact', 'Create contact.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'email' => ['type' => 'string']], 'required' => ['name']], 'write'),
            self::t('update_contact', 'Update contact.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_contact', 'Delete contact.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase2(): array
    {
        return [
            self::t('analyze_page_seo', 'SEO analysis for article.', ['type' => 'object', 'properties' => ['article_id' => ['type' => 'integer']], 'required' => ['article_id']], 'read'),
            self::t('update_article_seo_meta', 'Update article SEO meta.', ['type' => 'object', 'properties' => ['article_id' => ['type' => 'integer'], 'metadesc' => ['type' => 'string'], 'metakey' => ['type' => 'string'], 'robots' => ['type' => 'string']], 'required' => ['article_id']], 'write'),
            self::t('bulk_update_meta', 'Bulk update SEO meta.', ['type' => 'object', 'properties' => ['items' => ['type' => 'array']], 'required' => ['items']], 'write'),
            self::t('suggest_internal_links', 'Suggest internal links.', ['type' => 'object', 'properties' => ['article_id' => ['type' => 'integer']], 'required' => ['article_id']], 'read'),
            self::t('audit_duplicate_content', 'Find duplicate article titles.', ['type' => 'object'], 'read'),
            self::t('get_sitemap_status', 'Get sitemap and robots status.', ['type' => 'object'], 'read'),
            self::t('check_broken_links', 'Check broken links in articles.', ['type' => 'object'], 'read'),
            self::t('get_redirect_rules', 'List URL redirect rules.', ['type' => 'object'], 'read'),
            self::t('run_cache_clean', 'Clean Joomla cache.', ['type' => 'object'], 'write'),
            self::t('check_core_updates', 'Check Joomla core updates.', ['type' => 'object'], 'read'),
            self::t('get_site_health_extended', 'Extended site health report.', ['type' => 'object'], 'read'),
            self::t('get_performance_hints', 'Performance optimization hints.', ['type' => 'object'], 'read'),
            self::t('duplicate_sp_page', 'Duplicate SP Page Builder page.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string']], 'required' => ['id']], 'write'),
            self::t('publish_sp_page_to_menu', 'Add SP page to menu.', ['type' => 'object', 'properties' => ['page_id' => ['type' => 'integer'], 'menutype' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['page_id', 'menutype']], 'write'),
            self::t('get_helix_layout', 'Get Helix Ultimate layout params.', ['type' => 'object'], 'read'),
            self::t('update_helix_params', 'Update Helix Ultimate params.', ['type' => 'object', 'properties' => ['params' => ['type' => 'object']], 'required' => ['params']], 'write'),
            self::t('list_helix_positions', 'List Helix module positions.', ['type' => 'object'], 'read'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase3(): array
    {
        return [
            self::t('detect_installed_shops', 'Detect installed e-commerce extensions.', ['type' => 'object'], 'read'),
            self::t('virtuemart_list_products', 'List VirtueMart products.', ['type' => 'object'], 'read'),
            self::t('virtuemart_get_product', 'Get VirtueMart product.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('virtuemart_save_product', 'Save VirtueMart product.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']], 'write'),
            self::t('virtuemart_list_orders', 'List VirtueMart orders.', ['type' => 'object'], 'read'),
            self::t('hikashop_list_products', 'List HikaShop products.', ['type' => 'object'], 'read'),
            self::t('hikashop_get_product', 'Get HikaShop product.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('hikashop_save_product', 'Save HikaShop product.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']], 'write'),
            self::t('hikashop_list_orders', 'List HikaShop orders.', ['type' => 'object'], 'read'),
            self::t('j2commerce_list_products', 'List J2Commerce products.', ['type' => 'object'], 'read'),
            self::t('j2commerce_get_product', 'Get J2Commerce product.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('j2commerce_save_product', 'Save J2Commerce product.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']], 'write'),
            self::t('akeeba_list_backups', 'List Akeeba backups.', ['type' => 'object'], 'read'),
            self::t('akeeba_create_backup', 'Create Akeeba backup (CLI guide).', ['type' => 'object'], 'write'),
            self::t('admintools_security_status', 'Admin Tools security status.', ['type' => 'object'], 'read'),
            self::t('sh404sef_list_urls', 'List sh404SEF URLs.', ['type' => 'object'], 'read'),
            self::t('sh404sef_create_redirect', 'Create sh404SEF redirect.', ['type' => 'object', 'properties' => ['old_url' => ['type' => 'string'], 'new_url' => ['type' => 'string']], 'required' => ['old_url', 'new_url']], 'write'),
            self::t('jce_list_profiles', 'List JCE editor profiles.', ['type' => 'object'], 'read'),
            self::t('rsform_list_forms', 'List RSForm forms.', ['type' => 'object'], 'read'),
            self::t('rsform_list_submissions', 'List RSForm submissions.', ['type' => 'object', 'properties' => ['form_id' => ['type' => 'integer']], 'required' => ['form_id']], 'read'),
            self::t('acymailing_list_lists', 'List AcyMailing lists.', ['type' => 'object'], 'read'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase4(): array
    {
        return [
            self::t('create_pending_change', 'Queue change for admin approval.', ['type' => 'object', 'properties' => ['tool_name' => ['type' => 'string'], 'arguments' => ['type' => 'object'], 'description' => ['type' => 'string']], 'required' => ['tool_name', 'arguments']], 'write'),
            self::t('list_pending_changes', 'List pending changes.', ['type' => 'object'], 'read'),
            self::t('approve_pending_change', 'Approve pending change.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'write'),
            self::t('reject_pending_change', 'Reject pending change.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'reason' => ['type' => 'string']], 'required' => ['id']], 'write'),
            self::t('trigger_webhook', 'Trigger configured webhook.', ['type' => 'object', 'properties' => ['event' => ['type' => 'string'], 'payload' => ['type' => 'object']]], 'write'),
            self::t('list_webhook_events', 'List webhook event log.', ['type' => 'object'], 'read'),
            self::t('memory_store', 'Store persistent memory for AI sessions (Pro).', ['type' => 'object', 'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string'], 'context' => ['type' => 'string']], 'required' => ['key', 'value']], 'write'),
            self::t('memory_search', 'Search persistent memory (Pro).', ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'context' => ['type' => 'string']], 'required' => ['query']], 'read'),
            self::t('memory_list', 'List persistent memory entries (Pro).', ['type' => 'object', 'properties' => ['context' => ['type' => 'string'], 'limit' => ['type' => 'integer']]], 'read'),
        ];
    }

    /** @param array<string, mixed> $schema */
    private static function t(string $name, string $description, array $schema, string $risk): array
    {
        $readOnly = $risk === 'read';
        $destructive = $risk === 'destructive';

        return [
            'name'        => $name,
            'description' => $description,
            'inputSchema' => $schema,
            'annotations' => [
                'readOnlyHint'     => $readOnly,
                'destructiveHint'  => $destructive,
                'idempotentHint'   => $readOnly,
                'openWorldHint'    => in_array($risk, ['execute', 'write', 'destructive'], true),
            ],
        ];
    }
}
