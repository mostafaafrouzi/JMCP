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
            'build-landing-page'   => "Create a landing page titled '{title}' with goal '{goal}'. Check list_extensions for SP Page Builder. If installed use save_sp_page; otherwise create_article + create_menu_item. Add mod_custom for CTA if needed.",
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
