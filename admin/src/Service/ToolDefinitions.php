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
            self::phase4(),
            self::phase5(),
            self::phase6(),
            self::phase7(),
            self::phase8(),
            self::phase9(),
            self::phase10()
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
            self::t('create_article', 'Create article.', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'alias' => ['type' => 'string'], 'catid' => ['type' => 'integer'], 'introtext' => ['type' => 'string'], 'fulltext' => ['type' => 'string'], 'state' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['title', 'catid', 'introtext']], 'write'),
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
            self::t('get_sp_page', 'Get SP page (includes content JSON by default).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'include_content' => ['type' => 'boolean']], 'required' => ['id']], 'read'),
            self::t('save_sp_page', 'Create or update SP Page Builder page (supports content JSON column).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'layout' => ['type' => 'string'], 'css' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'og_title' => ['type' => 'string'], 'og_description' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']]], 'write'),
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
            self::t('virtuemart_list_products', 'List VirtueMart products with language table names.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer'], 'category_id' => ['type' => 'integer'], 'language' => ['type' => 'string']]], 'read'),
            self::t('virtuemart_get_product', 'Get VirtueMart product with language data.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'language' => ['type' => 'string']], 'required' => ['id']], 'read'),
            self::t('virtuemart_save_product', 'Save VirtueMart product (alias of virtuemart_update_product).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'short_description' => ['type' => 'string'], 'description' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']]], 'write'),
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

    /** @return array<int, array<string, mixed>> */
    private static function phase5(): array
    {
        return [
            self::t('list_users', 'List Joomla users.', ['type' => 'object', 'properties' => ['search' => ['type' => 'string'], 'block' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']]], 'read'),
            self::t('get_user', 'Get user by ID with groups.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('list_user_groups', 'List Joomla user groups.', ['type' => 'object'], 'read'),
            self::t('get_global_config', 'Read global site configuration (safe keys).', ['type' => 'object'], 'read'),
            self::t('list_banners', 'List site banners.', ['type' => 'object', 'properties' => ['state' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]], 'read'),
            self::t('list_newsfeeds', 'List news feeds.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]], 'read'),
            self::t('finder_search', 'Search indexed content via Smart Search.', ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'limit' => ['type' => 'integer']], 'required' => ['query']], 'read'),
            self::t('list_joomla_redirects', 'List Joomla core redirects (com_redirect).', ['type' => 'object', 'properties' => ['published' => ['type' => 'integer'], 'limit' => ['type' => 'integer']]], 'read'),
            self::t('create_joomla_redirect', 'Create Joomla core redirect.', ['type' => 'object', 'properties' => ['old_url' => ['type' => 'string'], 'new_url' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'comment' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['old_url', 'new_url']], 'write'),
            self::t('get_article_by_alias', 'Get article by URL alias.', ['type' => 'object', 'properties' => ['alias' => ['type' => 'string'], 'catid' => ['type' => 'integer']], 'required' => ['alias']], 'read'),
            self::t('assign_article_tags', 'Assign tags to an article.', ['type' => 'object', 'properties' => ['article_id' => ['type' => 'integer'], 'tag_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'dry_run' => ['type' => 'boolean']], 'required' => ['article_id', 'tag_ids']], 'write'),
            self::t('virtuemart_list_categories', 'List VirtueMart product categories.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]], 'read'),
            self::t('virtuemart_get_order', 'Get VirtueMart order with items.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('list_audit_log', 'List JMCP audit log entries.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]], 'read'),
            self::t('get_mcp_metrics', 'Get JMCP usage metrics summary.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]], 'read'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase6(): array
    {
        $replaceSchema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'from' => ['type' => 'string'],
                    'to'   => ['type' => 'string'],
                ],
                'required' => ['from', 'to'],
            ],
        ];

        return [
            self::t('update_global_config', 'Update Joomla global configuration (sitename, meta, mail, language).', ['type' => 'object', 'properties' => ['fields' => ['type' => 'object'], 'dry_run' => ['type' => 'boolean']], 'required' => ['fields']], 'write'),
            self::t('bulk_content_replace', 'Bulk find/replace text across site tables (presets: sp_pages, articles, menus, virtuemart_*).', ['type' => 'object', 'properties' => ['preset' => ['type' => 'string'], 'presets' => ['type' => 'array', 'items' => ['type' => 'string']], 'targets' => ['type' => 'object'], 'replacements' => $replaceSchema, 'where' => ['type' => 'object'], 'dry_run' => ['type' => 'boolean']], 'required' => ['replacements']], 'write'),
            self::t('search_site_content', 'Search for a text needle across site content tables.', ['type' => 'object', 'properties' => ['needle' => ['type' => 'string'], 'preset' => ['type' => 'string'], 'presets' => ['type' => 'array', 'items' => ['type' => 'string']], 'targets' => ['type' => 'object'], 'limit_per_column' => ['type' => 'integer']], 'required' => ['needle']], 'read'),
            self::t('site_rebrand', 'Guided site rebrand: global config + bulk text replace + optional VM language clone + cache clean.', ['type' => 'object', 'properties' => ['brand' => ['type' => 'string'], 'old_brand' => ['type' => 'string'], 'meta_desc' => ['type' => 'string'], 'presets' => ['type' => 'array', 'items' => ['type' => 'string']], 'clone_vm_language_tables' => ['type' => 'boolean'], 'vm_source_lang' => ['type' => 'string'], 'vm_target_lang' => ['type' => 'string'], 'clear_cache' => ['type' => 'boolean'], 'dry_run' => ['type' => 'boolean']], 'required' => ['brand']], 'write'),
            self::t('bulk_replace_sp_content', 'Bulk replace text inside SP Page Builder pages (content column).', ['type' => 'object', 'properties' => ['replacements' => $replaceSchema, 'page_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'dry_run' => ['type' => 'boolean']], 'required' => ['replacements']], 'write'),
            self::t('virtuemart_update_category', 'Update VirtueMart category name/description in language table.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'description' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'ordering' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id']], 'write'),
            self::t('virtuemart_update_product', 'Update VirtueMart product in language table (name, descriptions, slug).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'short_description' => ['type' => 'string'], 'description' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id']], 'write'),
            self::t('virtuemart_update_vendor', 'Update VirtueMart store/vendor info.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'store_description' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'language' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']]], 'write'),
            self::t('virtuemart_clone_language_tables', 'Clone VirtueMart *_en_gb tables to another language suffix (e.g. fa_ir).', ['type' => 'object', 'properties' => ['source_suffix' => ['type' => 'string'], 'target_suffix' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']]], 'write'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase7(): array
    {
        return [
            self::t('virtuemart_set_product_price', 'Set or update VirtueMart product price.', ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer'], 'price' => ['type' => 'number'], 'price_id' => ['type' => 'integer'], 'currency' => ['type' => 'integer'], 'override' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']], 'required' => ['product_id', 'price']], 'write'),
            self::t('virtuemart_assign_product_categories', 'Assign categories to a VirtueMart product.', ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer'], 'category_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'mode' => ['type' => 'string'], 'dry_run' => ['type' => 'boolean']], 'required' => ['product_id', 'category_ids']], 'write'),
            self::t('virtuemart_manage_product_media', 'List, attach, or detach VirtueMart product media.', ['type' => 'object', 'properties' => ['action' => ['type' => 'string'], 'product_id' => ['type' => 'integer'], 'media_id' => ['type' => 'integer'], 'link_id' => ['type' => 'integer'], 'ordering' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']], 'required' => ['product_id']], 'write'),
            self::t('virtuemart_get_config', 'Get VirtueMart shop configuration.', ['type' => 'object'], 'read'),
            self::t('virtuemart_set_config', 'Update VirtueMart shop configuration key/value pairs.', ['type' => 'object', 'properties' => ['config' => ['type' => 'object'], 'dry_run' => ['type' => 'boolean']], 'required' => ['config']], 'write'),
            self::t('virtuemart_list_custom_fields', 'List VirtueMart custom fields.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer'], 'published' => ['type' => 'integer']]], 'read'),
            self::t('virtuemart_set_custom_field', 'Create or update VirtueMart custom field.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'element' => ['type' => 'string'], 'field_type' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']]], 'write'),
            self::t('delete_sp_page', 'Delete an SP Page Builder page.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id']], 'destructive'),
            self::t('update_sp_page_meta', 'Update SP page meta (og_*, attribs, extension binding).', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'published' => ['type' => 'integer'], 'og_title' => ['type' => 'string'], 'og_description' => ['type' => 'string'], 'og_image' => ['type' => 'string'], 'attribs' => ['type' => 'object'], 'extension' => ['type' => 'string'], 'view_id' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']], 'required' => ['id']], 'write'),
            self::t('list_sp_page_modules', 'List mod_sppagebuilder modules (optionally by page_id).', ['type' => 'object', 'properties' => ['page_id' => ['type' => 'integer']]], 'read'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase8(): array
    {
        return [
            self::t('get_helix_menu_layout', 'Get Helix mega menu layout from menu item params.', ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer']], 'required' => ['menu_id']], 'read'),
            self::t('update_helix_menu_layout', 'Update Helix mega menu layout on a menu item.', ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer'], 'layout' => ['type' => 'object']], 'required' => ['menu_id', 'layout']], 'write'),
            self::t('list_template_positions', 'List module positions from templateDetails.xml.', ['type' => 'object', 'properties' => ['template' => ['type' => 'string'], 'client_id' => ['type' => 'integer']]], 'read'),
            self::t('update_ut_articles_module', 'Update mod_ut_articles_pro module settings.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'catid' => ['type' => 'integer'], 'count' => ['type' => 'integer'], 'position' => ['type' => 'string'], 'params' => ['type' => 'object']], 'required' => ['id']], 'write'),
            self::t('assign_module_to_menu', 'Assign module visibility to menu items (#__modules_menu).', ['type' => 'object', 'properties' => ['module_id' => ['type' => 'integer'], 'menu_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'mode' => ['type' => 'string']], 'required' => ['module_id']], 'write'),
            self::t('set_default_template_style', 'Set site or admin default template style.', ['type' => 'object', 'properties' => ['style_id' => ['type' => 'integer'], 'client_id' => ['type' => 'integer'], 'dry_run' => ['type' => 'boolean']], 'required' => ['style_id']], 'write'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase9(): array
    {
        return [
            self::t('update_component_params', 'Merge params into a Joomla component.', ['type' => 'object', 'properties' => ['option' => ['type' => 'string'], 'params' => ['type' => 'object'], 'dry_run' => ['type' => 'boolean']], 'required' => ['option', 'params']], 'write'),
            self::t('finder_rebuild_index', 'Rebuild Smart Search (Finder) index via CLI.', ['type' => 'object'], 'execute'),
            self::t('create_banner', 'Create com_banners banner.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'clickurl' => ['type' => 'string'], 'catid' => ['type' => 'integer'], 'state' => ['type' => 'integer']], 'required' => ['name']], 'write'),
            self::t('update_banner', 'Update banner fields.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_banner', 'Delete a banner.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('create_newsfeed', 'Create com_newsfeeds feed.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'link' => ['type' => 'string'], 'catid' => ['type' => 'integer']], 'required' => ['name', 'link']], 'write'),
            self::t('update_newsfeed', 'Update newsfeed fields.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_newsfeed', 'Delete a newsfeed.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('update_joomla_redirect', 'Update com_redirect link.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_joomla_redirect', 'Delete com_redirect link.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('create_user', 'Create Joomla user.', ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'username' => ['type' => 'string'], 'email' => ['type' => 'string'], 'password' => ['type' => 'string'], 'group_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]], 'required' => ['name', 'username', 'email']], 'write'),
            self::t('update_user', 'Update Joomla user fields.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('assign_user_groups', 'Assign user to groups.', ['type' => 'object', 'properties' => ['user_id' => ['type' => 'integer'], 'group_ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'mode' => ['type' => 'string']], 'required' => ['user_id', 'group_ids']], 'write'),
            self::t('toggle_extension', 'Enable or disable an extension.', ['type' => 'object', 'properties' => ['extension_id' => ['type' => 'integer'], 'element' => ['type' => 'string'], 'type' => ['type' => 'string'], 'enabled' => ['type' => 'boolean']], 'required' => ['enabled']], 'write'),
            self::t('list_scheduler_tasks', 'List Joomla scheduler tasks.', ['type' => 'object'], 'read'),
            self::t('run_scheduler_task', 'Run scheduler task(s) via CLI.', ['type' => 'object', 'properties' => ['task_id' => ['type' => 'integer']]], 'execute'),
            self::t('get_schemaorg_for_item', 'Get schema.org JSON for content item.', ['type' => 'object', 'properties' => ['item_id' => ['type' => 'integer'], 'context' => ['type' => 'string']], 'required' => ['item_id']], 'read'),
            self::t('update_schemaorg_for_item', 'Create or update schema.org for content item.', ['type' => 'object', 'properties' => ['item_id' => ['type' => 'integer'], 'context' => ['type' => 'string'], 'schema_type' => ['type' => 'string'], 'schema' => ['type' => 'object']], 'required' => ['item_id', 'schema']], 'write'),
            self::t('update_custom_field', 'Update Joomla custom field definition.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'fields' => ['type' => 'object']], 'required' => ['id', 'fields']], 'write'),
            self::t('delete_custom_field', 'Delete Joomla custom field.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function phase10(): array
    {
        return [
            self::t('install_extension', 'Install extension from zip package path.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']], 'execute'),
            self::t('update_extension', 'Update extension from zip package.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'extension_id' => ['type' => 'integer']], 'required' => ['path']], 'execute'),
            self::t('apply_joomla_update', 'Apply pending Joomla core update via CLI.', ['type' => 'object'], 'execute'),
            self::t('export_rsform_submissions', 'Export RSForm submissions as JSON or CSV.', ['type' => 'object', 'properties' => ['form_id' => ['type' => 'integer'], 'format' => ['type' => 'string']], 'required' => ['form_id']], 'read'),
            self::t('list_sp_collections', 'List SP Page Builder collections.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]], 'read'),
            self::t('get_sp_collection', 'Get SP collection with items.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'read'),
            self::t('save_sp_collection', 'Create or update SP collection.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'alias' => ['type' => 'string'], 'published' => ['type' => 'integer']], 'required' => ['title']], 'write'),
            self::t('delete_sp_collection', 'Delete SP collection and items.', ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']], 'destructive'),
            self::t('configure_webhook', 'Configure JMCP webhook URL/secret.', ['type' => 'object', 'properties' => ['url' => ['type' => 'string'], 'secret' => ['type' => 'string'], 'enabled' => ['type' => 'boolean']]], 'write'),
            self::t('get_webhook_config', 'Read JMCP webhook configuration (no secret).', ['type' => 'object'], 'read'),
            self::t('create_site_snapshot', 'Export key site tables to JSON snapshot.', ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'tables' => ['type' => 'array', 'items' => ['type' => 'string']]]], 'write'),
            self::t('restore_site_snapshot', 'Restore site tables from JSON snapshot.', ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'tables' => ['type' => 'array', 'items' => ['type' => 'string']], 'dry_run' => ['type' => 'boolean']], 'required' => ['path']], 'destructive'),
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
