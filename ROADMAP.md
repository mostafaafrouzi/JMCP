# JMCP Development Roadmap

| Phase | Version | Focus | Status |
|-------|---------|-------|--------|
| **0** | 1.0.x | Core MCP, security, dashboard | ✅ Done |
| **1** | 1.0.0 | Joomla core: media, versions, multilingual, templates | ✅ Done |
| **2** | 1.0.0 | SEO, SP Builder, Helix, health checks | ✅ Done |
| **3** | 1.0.0 | E-commerce + extension integrations | ✅ Done |
| **4** | 1.0.0 | Resources, prompts, pending queue, webhooks | ✅ Done |
| **5** | 1.0.0 | Maintenance, rebrand, bulk replace | ✅ Done |
| **6** | 1.0.0 | VM/SP deep tools, site ops | ✅ Done |
| **7** | 1.0.0 | VM commerce, SP meta, collections | ✅ Done |
| **8** | 1.0.0 | Helix/UT, template positions | ✅ Done |
| **9** | 1.0.0 | Users, banners, scheduler, schema.org | ✅ Done |
| **10** | 1.0.0 | Snapshots, extension install, RSForm export | ✅ Done |
| **Future** | 1.1+ | License server, payment, deeper extension APIs | Planned |

---

## v1.0.0 Release Scope (~200 MCP tools)

### Content & maintenance
- Full article/category/menu/module CRUD
- `bulk_content_replace`, `search_site_content`, `site_rebrand`
- `update_global_config` (incl. site language via com_languages)

### VirtueMart
- Products, categories, vendor, prices, media, custom fields, config
- Language table clone (`virtuemart_clone_language_tables`)

### SP Page Builder
- Pages, meta, modules, collections, bulk content replace

### Helix / templates
- Params, menu layout, template style merge (non-destructive)
- `list_template_positions`, `set_default_template_style`

### SEO & platform
- Redirects, schema.org, finder index (CLI), scheduler
- Users, banners, newsfeeds, component params
- Webhooks, audit log, pending changes

### Security defaults
- SQL, PHP, file write, CLI **off** by default
- Content/shop/rebrand tools work without elevated permissions

---

## Known limitations (v1.0.0)

| Item | Workaround |
|------|------------|
| SEF alias URLs | Use `id=` links or configure Joomla SEF + menu |
| Media upload | Enable `allow_file_write` |
| Cache / finder CLI | Enable `allow_cli_exec` |
| Raw SQL | Enable `allow_sql_exec` (not recommended) |
| Pro tools | `virtuemart_list_orders`, webhooks, memory, some backups |

---

## Post-1.0.0 planned

- Automatic SEF menu binding for new articles
- `set_site_language` as dedicated tool
- Media bulk import from folder
- License / Pro payment gateway
- Deeper Akeeba/RSForm automation
