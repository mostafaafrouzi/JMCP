# Changelog

All notable changes to JMCP are documented here.

## [1.2.0] — 2026-06-12

### Added — SP Page Builder designer tools

Native visual-editor workflow (Row → Column → Addon) without `raw_html`:

- `sp_add_row`, `sp_add_addon`, `sp_set_addon_field`, `sp_set_row_field`, `sp_set_column_field`
- `sp_set_addon_style_tab`, `sp_get_addon_blueprint`, `sp_get_page_tree`, `sp_get_node`
- `sp_clone_row`, `sp_clone_addon`, `sp_move_node`, `sp_insert_section`, `sp_list_section_presets`
- `sp_add_repeatable_item`, `sp_bulk_set_addon_field`, `sp_set_addon_media`
- `sp_validate_page`, `sp_save_page_design`, `sp_preview_page`, `sp_create_page_from_template`
- `sp_repair_page_layout` — fixes missing column `width` on legacy MCP-built pages
- `sp_set_page_css` — supports inline `css` or server-side `media_path` (large stylesheets)

New PHP services: `SpPageTree`, `SpAddonRegistry`, `SpPageSaveService`, `SpPageValidator`, `SpSectionLibrary`, `SpDesignerExecutor`.

### Fixed

- Column `settings.width` now set from layout (`12` → 100%, `6.0+6.0` → 50%) so SP editor renders rows correctly
- `sp_save_page_design` syncs `content` and `text` fields for SP admin editor compatibility
- `save_sp_page` sets default `css` on new pages (no SQL default-value error)
- `sp_set_page_css` uses direct DB update (`saveCssOnly`) — no hang on large CSS
- Addon blueprint no longer polluted from unrelated site templates when `template_page_id` omitted
- `text_block` title auto-cleared when `text` is set without `title`

### Changed

- `design-sp-page` and `build-landing-page` MCP skills updated for native designer workflow
- Local dev scripts moved from `tools/` to `dev/` (not packaged in `com_jmcp.zip`)
- `update.xml` points to v1.2.0 release artifact

## [1.0.0] — 2026-06-12

First public release: ~200 MCP tools, Bearer auth, policy gates, VirtueMart, Helix, SEO, rebrand, maintenance.
