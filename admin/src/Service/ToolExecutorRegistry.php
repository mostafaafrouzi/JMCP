<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Component\Jmcp\Administrator\Service\Executor\BannerExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\CodeExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ContactExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ExtensionExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\SnapshotExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ContentExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\CustomFieldExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\DatabaseExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\DiscoverExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ExtensionIntegrationsExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\FilesystemExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\FinderExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\HelixExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\JmcpAdminExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\MaintenanceExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\MediaExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\MemoryExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\MenuExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ModuleExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\MultilingualExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\PluginExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\SeoExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\ShopExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\SiteExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\SpPageExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\SystemExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\TagExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\TemplateExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\UserExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\VersionExecutor;
use Joomla\Component\Jmcp\Administrator\Service\Executor\WorkflowExecutor;
use Joomla\Registry\Registry;

class ToolExecutorRegistry
{
    public function register(ToolRegistry $registry, Registry $params): void
    {
        $content     = new ContentExecutor();
        $menu        = new MenuExecutor();
        $module      = new ModuleExecutor();
        $plugin      = new PluginExecutor();
        $filesystem  = new FilesystemExecutor();
        $database    = new DatabaseExecutor();
        $spPage      = new SpPageExecutor();
        $code        = new CodeExecutor();
        $site        = new SiteExecutor();
        $media       = new MediaExecutor();
        $version     = new VersionExecutor();
        $multilingual= new MultilingualExecutor();
        $template    = new TemplateExecutor();
        $tag         = new TagExecutor();
        $fields      = new CustomFieldExecutor();
        $contact     = new ContactExecutor();
        $seo         = new SeoExecutor();
        $helix       = new HelixExecutor();
        $shop        = new ShopExecutor();
        $extensions  = new ExtensionIntegrationsExecutor();
        $discover    = new DiscoverExecutor();
        $system      = new SystemExecutor();
        $workflow    = new WorkflowExecutor();
        $memory      = new MemoryExecutor();
        $users       = new UserExecutor();
        $finder      = new FinderExecutor();
        $jmcpAdmin   = new JmcpAdminExecutor();
        $maintenance = new MaintenanceExecutor();
        $banners     = new BannerExecutor();
        $extensionMgr= new ExtensionExecutor();
        $snapshot    = new SnapshotExecutor();

        $map = [
            'discover_tools' => fn(array $p) => $discover->discoverTools($p, $registry, $params),
            'get_site_info' => [$site, 'getSiteInfo'],
            'list_extensions' => [$site, 'listExtensions'],
            'list_template_styles' => [$site, 'listTemplateStyles'],
            'list_tags' => [$site, 'listTags'],
            'get_component_params' => [$site, 'getComponentParams'],
            'update_component_params' => [$site, 'updateComponentParams'],
            'toggle_extension' => [$site, 'toggleExtension'],
            'get_global_config' => [$site, 'getGlobalConfig'],
            'update_global_config' => [$site, 'updateGlobalConfig'],
            'list_banners' => [$site, 'listBanners'],
            'list_newsfeeds' => [$site, 'listNewsfeeds'],
            'list_articles' => [$content, 'listArticles'],
            'get_article' => [$content, 'getArticle'],
            'get_article_by_alias' => [$content, 'getArticleByAlias'],
            'assign_article_tags' => [$content, 'assignArticleTags'],
            'create_article' => [$content, 'createArticle'],
            'update_article' => [$content, 'updateArticle'],
            'delete_article' => [$content, 'deleteArticle'],
            'list_categories' => [$content, 'listCategories'],
            'get_category' => [$content, 'getCategory'],
            'create_category' => [$content, 'createCategory'],
            'update_category' => [$content, 'updateCategory'],
            'delete_category' => [$content, 'deleteCategory'],
            'list_menus' => [$menu, 'listMenus'],
            'list_menu_items' => [$menu, 'listMenuItems'],
            'get_menu_item' => [$menu, 'getMenuItem'],
            'create_menu_item' => [$menu, 'createMenuItem'],
            'update_menu_item' => [$menu, 'updateMenuItem'],
            'delete_menu_item' => [$menu, 'deleteMenuItem'],
            'list_modules' => [$module, 'listModules'],
            'get_module' => [$module, 'getModule'],
            'create_module' => [$module, 'createModule'],
            'update_module' => [$module, 'updateModule'],
            'delete_module' => [$module, 'deleteModule'],
            'assign_module_to_menu' => [$module, 'assignModuleToMenu'],
            'update_ut_articles_module' => [$module, 'updateUtArticlesModule'],
            'list_plugins' => [$plugin, 'listPlugins'],
            'toggle_plugin_state' => [$plugin, 'togglePluginState'],
            'update_plugin_params' => [$plugin, 'updatePluginParams'],
            'list_directory' => [$filesystem, 'listDirectory'],
            'read_file' => [$filesystem, 'readFile'],
            'write_file' => [$filesystem, 'writeFile'],
            'edit_file' => [$filesystem, 'editFile'],
            'delete_file' => [$filesystem, 'deleteFile'],
            'list_db_tables' => [$database, 'listDbTables'],
            'get_db_table_columns' => [$database, 'getDbTableColumns'],
            'execute_sql' => [$database, 'executeSql'],
            'list_sp_pages' => [$spPage, 'listSpPages'],
            'get_sp_page' => [$spPage, 'getSpPage'],
            'save_sp_page' => [$spPage, 'saveSpPage'],
            'bulk_replace_sp_content' => [$spPage, 'bulkReplaceSpContent'],
            'delete_sp_page' => [$spPage, 'deleteSpPage'],
            'update_sp_page_meta' => [$spPage, 'updateSpPageMeta'],
            'list_sp_page_modules' => [$spPage, 'listSpPageModules'],
            'list_sp_collections' => [$spPage, 'listSpCollections'],
            'get_sp_collection' => [$spPage, 'getSpCollection'],
            'save_sp_collection' => [$spPage, 'saveSpCollection'],
            'delete_sp_collection' => [$spPage, 'deleteSpCollection'],
            'duplicate_sp_page' => [$spPage, 'duplicateSpPage'],
            'publish_sp_page_to_menu' => [$spPage, 'publishSpPageToMenu'],
            'execute_php' => [$code, 'executePhp'],
            'run_cli_command' => [$code, 'runCliCommand'],
            'list_media' => [$media, 'listMedia'],
            'get_media' => [$media, 'getMedia'],
            'upload_media' => [$media, 'uploadMedia'],
            'create_media_folder' => [$media, 'createMediaFolder'],
            'update_media' => [$media, 'updateMedia'],
            'delete_media' => [$media, 'deleteMedia'],
            'list_article_versions' => [$version, 'listArticleVersions'],
            'get_article_version' => [$version, 'getArticleVersion'],
            'restore_article_version' => [$version, 'restoreArticleVersion'],
            'delete_article_version' => [$version, 'deleteArticleVersion'],
            'keep_article_version' => [$version, 'keepArticleVersion'],
            'list_content_languages' => [$multilingual, 'listContentLanguages'],
            'get_content_language' => [$multilingual, 'getContentLanguage'],
            'create_content_language' => [$multilingual, 'createContentLanguage'],
            'update_content_language' => [$multilingual, 'updateContentLanguage'],
            'list_article_associations' => [$multilingual, 'listArticleAssociations'],
            'set_article_associations' => [$multilingual, 'setArticleAssociations'],
            'list_menu_item_associations' => [$multilingual, 'listMenuItemAssociations'],
            'set_menu_item_associations' => [$multilingual, 'setMenuItemAssociations'],
            'list_installed_templates' => [$template, 'listInstalledTemplates'],
            'get_template_style' => [$template, 'getTemplateStyle'],
            'create_template_style' => [$template, 'createTemplateStyle'],
            'update_template_style' => [$template, 'updateTemplateStyle'],
            'delete_template_style' => [$template, 'deleteTemplateStyle'],
            'list_template_positions' => [$template, 'listTemplatePositions'],
            'set_default_template_style' => [$template, 'setDefaultTemplateStyle'],
            'create_template_override' => [$template, 'createTemplateOverride'],
            'create_tag' => [$tag, 'createTag'],
            'update_tag' => [$tag, 'updateTag'],
            'delete_tag' => [$tag, 'deleteTag'],
            'list_custom_fields' => [$fields, 'listCustomFields'],
            'get_custom_field' => [$fields, 'getCustomField'],
            'create_custom_field' => [$fields, 'createCustomField'],
            'update_field_values' => [$fields, 'updateFieldValues'],
            'update_custom_field' => [$fields, 'updateCustomField'],
            'delete_custom_field' => [$fields, 'deleteCustomField'],
            'list_contacts' => [$contact, 'listContacts'],
            'get_contact' => [$contact, 'getContact'],
            'create_contact' => [$contact, 'createContact'],
            'update_contact' => [$contact, 'updateContact'],
            'delete_contact' => [$contact, 'deleteContact'],
            'analyze_page_seo' => [$seo, 'analyzePageSeo'],
            'update_article_seo_meta' => [$seo, 'updateArticleSeoMeta'],
            'bulk_update_meta' => [$seo, 'bulkUpdateMeta'],
            'suggest_internal_links' => [$seo, 'suggestInternalLinks'],
            'audit_duplicate_content' => [$seo, 'auditDuplicateContent'],
            'get_sitemap_status' => [$seo, 'getSitemapStatus'],
            'check_broken_links' => [$seo, 'checkBrokenLinks'],
            'get_redirect_rules' => [$seo, 'getRedirectRules'],
            'list_joomla_redirects' => [$seo, 'listJoomlaRedirects'],
            'create_joomla_redirect' => [$seo, 'createJoomlaRedirect'],
            'update_joomla_redirect' => [$seo, 'updateJoomlaRedirect'],
            'delete_joomla_redirect' => [$seo, 'deleteJoomlaRedirect'],
            'get_schemaorg_for_item' => [$seo, 'getSchemaorgForItem'],
            'update_schemaorg_for_item' => [$seo, 'updateSchemaorgForItem'],
            'finder_search' => [$finder, 'finderSearch'],
            'run_cache_clean' => [$system, 'runCacheClean'],
            'check_core_updates' => [$system, 'checkCoreUpdates'],
            'get_site_health_extended' => [$system, 'getSiteHealthExtended'],
            'get_performance_hints' => [$system, 'getPerformanceHints'],
            'finder_rebuild_index' => [$system, 'finderRebuildIndex'],
            'apply_joomla_update' => [$system, 'applyJoomlaUpdate'],
            'list_scheduler_tasks' => [$system, 'listSchedulerTasks'],
            'run_scheduler_task' => [$system, 'runSchedulerTask'],
            'get_helix_layout' => [$helix, 'getHelixLayout'],
            'update_helix_params' => [$helix, 'updateHelixParams'],
            'list_helix_positions' => [$helix, 'listHelixPositions'],
            'get_helix_menu_layout' => [$helix, 'getHelixMenuLayout'],
            'update_helix_menu_layout' => [$helix, 'updateHelixMenuLayout'],
            'detect_installed_shops' => [$shop, 'detectInstalledShops'],
            'virtuemart_list_products' => [$shop, 'virtuemartListProducts'],
            'virtuemart_get_product' => [$shop, 'virtuemartGetProduct'],
            'virtuemart_save_product' => [$shop, 'virtuemartSaveProduct'],
            'virtuemart_list_orders' => [$shop, 'virtuemartListOrders'],
            'virtuemart_list_categories' => [$shop, 'virtuemartListCategories'],
            'virtuemart_update_category' => [$shop, 'virtuemartUpdateCategory'],
            'virtuemart_update_product' => [$shop, 'virtuemartUpdateProduct'],
            'virtuemart_update_vendor' => [$shop, 'virtuemartUpdateVendor'],
            'virtuemart_clone_language_tables' => [$shop, 'virtuemartCloneLanguageTables'],
            'virtuemart_set_product_price' => [$shop, 'virtuemartSetProductPrice'],
            'virtuemart_assign_product_categories' => [$shop, 'virtuemartAssignProductCategories'],
            'virtuemart_manage_product_media' => [$shop, 'virtuemartManageProductMedia'],
            'virtuemart_get_config' => [$shop, 'virtuemartGetConfig'],
            'virtuemart_set_config' => [$shop, 'virtuemartSetConfig'],
            'virtuemart_list_custom_fields' => [$shop, 'virtuemartListCustomFields'],
            'virtuemart_set_custom_field' => [$shop, 'virtuemartSetCustomField'],
            'virtuemart_get_order' => [$shop, 'virtuemartGetOrder'],
            'bulk_content_replace' => [$maintenance, 'bulkContentReplace'],
            'search_site_content' => [$maintenance, 'searchSiteContent'],
            'site_rebrand' => [$maintenance, 'siteRebrand'],
            'hikashop_list_products' => [$shop, 'hikashopListProducts'],
            'hikashop_get_product' => [$shop, 'hikashopGetProduct'],
            'hikashop_save_product' => [$shop, 'hikashopSaveProduct'],
            'hikashop_list_orders' => [$shop, 'hikashopListOrders'],
            'j2commerce_list_products' => [$shop, 'j2commerceListProducts'],
            'j2commerce_get_product' => [$shop, 'j2commerceGetProduct'],
            'j2commerce_save_product' => [$shop, 'j2commerceSaveProduct'],
            'akeeba_list_backups' => [$extensions, 'akeebaListBackups'],
            'akeeba_create_backup' => [$extensions, 'akeebaCreateBackup'],
            'admintools_security_status' => [$extensions, 'admintoolsSecurityStatus'],
            'sh404sef_list_urls' => [$extensions, 'sh404sefListUrls'],
            'sh404sef_create_redirect' => [$extensions, 'sh404sefCreateRedirect'],
            'jce_list_profiles' => [$extensions, 'jceListProfiles'],
            'rsform_list_forms' => [$extensions, 'rsformListForms'],
            'rsform_list_submissions' => [$extensions, 'rsformListSubmissions'],
            'export_rsform_submissions' => [$extensions, 'exportRsformSubmissions'],
            'install_extension' => [$extensionMgr, 'installExtension'],
            'update_extension' => [$extensionMgr, 'updateExtension'],
            'create_banner' => [$banners, 'createBanner'],
            'update_banner' => [$banners, 'updateBanner'],
            'delete_banner' => [$banners, 'deleteBanner'],
            'create_newsfeed' => [$banners, 'createNewsfeed'],
            'update_newsfeed' => [$banners, 'updateNewsfeed'],
            'delete_newsfeed' => [$banners, 'deleteNewsfeed'],
            'create_site_snapshot' => [$snapshot, 'createSiteSnapshot'],
            'restore_site_snapshot' => [$snapshot, 'restoreSiteSnapshot'],
            'acymailing_list_lists' => [$extensions, 'acymailingListLists'],
            'create_pending_change' => [$workflow, 'createPendingChange'],
            'list_pending_changes' => [$workflow, 'listPendingChanges'],
            'approve_pending_change' => fn(array $p) => $workflow->approvePendingChange($p, $registry),
            'reject_pending_change' => [$workflow, 'rejectPendingChange'],
            'trigger_webhook' => [$workflow, 'triggerWebhook'],
            'list_webhook_events' => [$workflow, 'listWebhookEvents'],
            'configure_webhook' => [$workflow, 'configureWebhook'],
            'get_webhook_config' => [$workflow, 'getWebhookConfig'],
            'memory_store'  => [$memory, 'memoryStore'],
            'memory_search' => [$memory, 'memorySearch'],
            'memory_list'   => [$memory, 'memoryList'],
            'list_users' => [$users, 'listUsers'],
            'get_user' => [$users, 'getUser'],
            'list_user_groups' => [$users, 'listUserGroups'],
            'create_user' => [$users, 'createUser'],
            'update_user' => [$users, 'updateUser'],
            'assign_user_groups' => [$users, 'assignUserGroups'],
            'list_audit_log' => [$jmcpAdmin, 'listAuditLog'],
            'get_mcp_metrics' => [$jmcpAdmin, 'getMcpMetrics'],
        ];

        foreach ($map as $name => $callable) {
            $registry->setExecutor($name, function (array $p) use ($callable, $name): mixed {
                if (is_callable($callable) && !is_array($callable)) {
                    return $callable($p);
                }
                return ($callable[0])->{$callable[1]}($p);
            });
        }

        foreach ($registry->getAll() as $tool) {
            if (!$registry->hasExecutor($tool['name'])) {
                throw new \LogicException(sprintf("Tool '%s' has a schema but no executor.", $tool['name']));
            }
        }
    }
}
