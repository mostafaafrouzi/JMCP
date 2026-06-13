<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Built-in MCP prompts / skills for AI agents.
 */
class SkillRegistry
{
    /** @return array<int, array<string, mixed>> */
    public function getPrompts(): array
    {
        return [
            [
                'name'        => 'optimize-article-seo',
                'description' => 'Analyze and optimize a Joomla article for SEO (meta, headings, internal links).',
                'arguments'   => [
                    ['name' => 'article_id', 'description' => 'Article ID to optimize', 'required' => true],
                ],
            ],
            [
                'name'        => 'build-landing-page',
                'description' => 'Create a landing page using SP Page Builder or custom HTML module.',
                'arguments'   => [
                    ['name' => 'title', 'description' => 'Page title', 'required' => true],
                    ['name' => 'goal', 'description' => 'Conversion goal (signup, purchase, contact)', 'required' => false],
                ],
            ],
            [
                'name'        => 'setup-multilingual',
                'description' => 'Configure multilingual content: languages, associations, menu items.',
                'arguments'   => [
                    ['name' => 'languages', 'description' => 'Comma-separated language codes (e.g. fa-IR,en-GB)', 'required' => true],
                ],
            ],
            [
                'name'        => 'audit-site-health',
                'description' => 'Run a full site health and SEO audit.',
                'arguments'   => [],
            ],
            [
                'name'        => 'create-template-override',
                'description' => 'Create a Joomla template override for a component view.',
                'arguments'   => [
                    ['name' => 'component', 'description' => 'Component (e.g. com_content)', 'required' => true],
                    ['name' => 'view', 'description' => 'View name (e.g. article)', 'required' => true],
                ],
            ],
            [
                'name'        => 'manage-shop-product',
                'description' => 'Create or update a product in the detected e-commerce extension.',
                'arguments'   => [
                    ['name' => 'name', 'description' => 'Product name', 'required' => true],
                    ['name' => 'price', 'description' => 'Product price', 'required' => false],
                ],
            ],
            [
                'name'        => 'design-sp-page',
                'description' => 'Design or edit SP Page Builder pages like the visual editor (rows, addons, fields, styles).',
                'arguments'   => [
                    ['name' => 'page_id', 'description' => 'SP page ID (or 0 to create from template)', 'required' => false],
                    ['name' => 'goal', 'description' => 'What to build or change', 'required' => true],
                ],
            ],
            [
                'name'        => 'rebrand-site',
                'description' => 'Rebrand entire Joomla site: config, articles, SP pages, menus, VirtueMart, modules.',
                'arguments'   => [
                    ['name' => 'brand', 'description' => 'New brand/site name', 'required' => true],
                    ['name' => 'old_brand', 'description' => 'Previous brand to replace', 'required' => false],
                ],
            ],
        ];
    }

    public function getPromptContent(string $name, array $args = []): ?string
    {
        $templates = [
            'optimize-article-seo' => "You are a Joomla SEO expert. Use analyze_page_seo and get_article on article ID {article_id}. Update meta description, title length, H1, internal links. Use update_article_seo_meta. Report changes in Persian if site is fa-IR.",
            'build-landing-page'   => "Create a landing page titled '{title}' with goal '{goal}'. Use save_sp_page (empty content) or sp_create_page_from_template, then sp_add_row + sp_add_addon (native addons only: heading, text_block, button, image — never raw_html). sp_validate_page then sp_save_page_design. Optional: upload_media + sp_set_page_css with media_path. Publish with create_menu_item.",
            'design-sp-page'       => "SP Page Builder designer workflow for: {goal}. Page ID: {page_id}. Native structure only (Row → Column → Addon). Steps: 1) sp_list_addons. 2) sp_get_addon_blueprint (no template_page_id unless cloning styles). 3) New page: save_sp_page title + content=[] OR sp_create_page_from_template. 4) Build: sp_add_row (layout e.g. 12 or 6.0+6.0), sp_add_addon, sp_set_addon_field, sp_set_addon_style_tab, sp_set_column_field, sp_set_row_field. 5) sp_validate_page then sp_save_page_design (syncs content+text). 6) Styling: upload_media then sp_set_page_css with media_path (not inline css for large files). 7) sp_preview_page. Repair legacy pages: sp_repair_page_layout. Paths: rows[0].columns[0].addons[0]. Use dry_run=true on writes.",
            'setup-multilingual'   => "Set up multilingual site for languages: {languages}. Use list_content_languages, create_content_language, set_article_associations, set_menu_item_associations.",
            'audit-site-health'    => "Run get_site_health_extended, get_sitemap_status, audit_duplicate_content, check_broken_links, check_core_updates. Produce prioritized action list.",
            'create-template-override' => "Use create_template_override for {component} view {view}. Read existing layout with read_file. Write minimal override.",
            'manage-shop-product'  => "Use detect_installed_shops first. Create/update product '{name}' price '{price}' with virtuemart_update_product or matching shop tools.",
            'rebrand-site'         => "Rebrand site to '{brand}'. 1) site_rebrand with dry_run=true first. 2) search_site_content for old text. 3) bulk_content_replace on sp_pages/articles/menus. 4) update_global_config. 5) virtuemart_clone_language_tables if fa-IR. 6) run_cache_clean. Use update_article/update_menu_item for per-item fixes.",
        ];

        if (!isset($templates[$name])) {
            return null;
        }

        $content = $templates[$name];
        foreach ($args as $key => $value) {
            $content = str_replace('{' . $key . '}', (string) $value, $content);
        }

        return $content;
    }
}
