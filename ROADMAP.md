# JMCP Development Roadmap

Full phased plan for JMCP — Joomla Model Context Protocol Server.

| Phase | Version | Focus | Status |
|-------|---------|-------|--------|
| **0** | 1.0.x | Core MCP, basic tools, security, dashboard | ✅ Done |
| **1** | 2.0.0 | Joomla core gaps: media, versions, multilingual, templates, tags, fields, contacts | ✅ In 2.0.0 |
| **2** | 2.1.0 | SEO suite, SP Builder advanced, Helix, discover_tools, security hardening | ✅ In 2.0.0 |
| **3** | 2.2.0 | E-commerce + popular extension integrations | ✅ In 2.0.0 |
| **4** | 2.1.0 | MCP Resources/Prompts, pending queue UI, audit UI, Pro tier structure | ✅ Done |
| **5** | 2.2.0 | SQL migrations, media API wrapper, production safety, webhooks, health endpoint | ✅ Done |
| **6** | Future | Payment gateway, license server, deep extension APIs | Planned |

---

## Phase 0 — Foundation (v1.0.x) ✅

- HTTP JSON-RPC 2.0 + SSE transport
- Bearer auth, IP allowlist, rate limiting, CORS
- Articles, categories, menus, modules, plugins
- Filesystem, SQL, PHP sandbox, CLI
- SP Page Builder basic, metrics dashboard
- `mcp-http-bridge.js`, GitHub Release workflow
- Bilingual: en-GB + fa-IR

---

## Phase 1 — Joomla Core Completeness (v2.0.0)

### Media Manager
- `list_media`, `get_media`, `upload_media`
- `create_media_folder`, `update_media`, `delete_media`

### Content History
- `list_article_versions`, `get_article_version`
- `restore_article_version`, `delete_article_version`, `keep_article_version`

### Multilingual
- `list_content_languages`, `get_content_language`
- `create_content_language`, `update_content_language`
- `list_article_associations`, `set_article_associations`
- `list_menu_item_associations`, `set_menu_item_associations`

### Templates
- `list_installed_templates`
- `get_template_style`, `create_template_style`, `update_template_style`, `delete_template_style`
- `create_template_override`

### Tags, Fields, Contacts
- `create_tag`, `update_tag`, `delete_tag`
- `list_custom_fields`, `get_custom_field`, `create_custom_field`, `update_field_values`
- `list_contacts`, `get_contact`, `create_contact`, `update_contact`, `delete_contact`

### Quality
- HTML sanitization on article write
- Abilities Hub (per-tool toggle)
- Audit log

---

## Phase 2 — SEO & Webmaster (v2.1.0)

### SEO Tools
- `analyze_page_seo` — title, meta, H1, canonical, schema hints
- `update_article_seo_meta`, `bulk_update_meta`
- `suggest_internal_links`, `audit_duplicate_content`
- `get_sitemap_status`, `check_broken_links`, `get_redirect_rules`

### Performance & Health
- `run_cache_clean`, `check_core_updates`
- `get_site_health_extended`, `get_performance_hints`

### Page Builders & Frameworks
- `duplicate_sp_page`, `publish_sp_page_to_menu`
- `get_helix_layout`, `update_helix_params`, `list_helix_positions`

### AI Experience
- `discover_tools` — rich context + installed extensions + active tools
- Built-in Skills (prompts)
- Production warning + domain lock

---

## Phase 3 — Extension Integrations (v2.2.0)

### E-commerce (auto-detected)
- **VirtueMart**: `virtuemart_list_products`, `virtuemart_get_product`, `virtuemart_save_product`, `virtuemart_list_orders`
- **HikaShop**: `hikashop_list_products`, `hikashop_get_product`, `hikashop_save_product`, `hikashop_list_orders`
- **J2Commerce**: `j2commerce_list_products`, `j2commerce_get_product`, `j2commerce_save_product`
- `detect_installed_shops`

### Other Extensions
- **Akeeba Backup**: `akeeba_list_backups`, `akeeba_create_backup`
- **Admin Tools**: `admintools_security_status`
- **sh404SEF**: `sh404sef_list_urls`, `sh404sef_create_redirect`
- **JCE**: `jce_list_profiles`
- **RSForm**: `rsform_list_forms`, `rsform_list_submissions`
- **AcyMailing**: `acymailing_list_lists`

---

## Phase 4 — Platform & Monetization (v2.3.0)

### MCP Protocol Extensions
- `resources/list` — site config, active template, extensions manifest
- `prompts/list` — built-in skills (SEO, landing page, multilingual setup)

### Workflow
- Pending changes queue: `create_pending_change`, `list_pending_changes`, `approve_pending_change`
- Webhooks: `configure_webhook`, `list_webhook_events`
- Dry-run mode on destructive tools (`dry_run=true`)

### Distribution
- Joomla `update.xml` → GitHub Releases
- Pro tier hooks (`JMCP_PRO_VERSION` constant detection)
- Persistent memory table (Pro)

---

## Architecture

```
admin/src/Service/
├── ToolDefinitions.php      # All tool schemas (single source of truth)
├── ToolRegistry.php         # Runtime registry
├── ToolExecutorRegistry.php # Wires executors
├── PolicyService.php        # Capabilities + AbilityHub + domain lock
├── IntegrationDetector.php  # Detects installed extensions
├── AbilityHubService.php    # Per-tool enable/disable
├── SkillRegistry.php        # MCP prompts / skills
├── ResourceProvider.php     # MCP resources
├── AuditService.php         # Change audit trail
├── PendingChangesService.php
├── WebhookService.php
├── HtmlSanitizer.php
└── Executor/                # One class per domain
```

---

## Pro Tier (Future)

| Free | Pro |
|------|-----|
| All core + basic integrations | Advanced shop/page-builder automation |
| Standard skills | Custom skills + persistent memory |
| Community support | Priority integrations |

Detection (implemented in 2.1.0):
- `TierRegistry` — lists Free vs Pro tools
- `LicenseService` — constant, companion plugin, license key hook
- `FeatureGate` — blocks Pro tools when not licensed
- `PersistentMemoryService` + `#__jmcp_memory` table
- Companion reference: `extras/plg_system_jmcppro/`

Payment processing: **not implemented** (by design until needed).
