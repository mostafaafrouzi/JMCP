# JMCP — Joomla Model Context Protocol Server

[![Joomla](https://img.shields.io/badge/Joomla-4.x%20%7C%205.x%20%7C%206.x-blue)](https://www.joomla.org)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)
[![Release](https://img.shields.io/github/v/release/mostafaafrouzi/JMCP?label=release)](https://github.com/mostafaafrouzi/JMCP/releases)

**JMCP** is a native Joomla component that exposes a full [Model Context Protocol (MCP)](https://modelcontextprotocol.io) server on your site. Connect Cursor, Claude Desktop, Claude Code, or any MCP client and let AI manage content, menus, modules, shops, page builders, SEO, and more — **without phpMyAdmin or direct file access** for everyday tasks.

---

### [فارسی]

**JMCP** سرور MCP نیتیو روی جوملا است. هوش مصنوعی می‌تواند مقالات، منوها، ماژول‌ها، VirtueMart، SP Page Builder، Helix و تنظیمات سایت را از طریق API امن مدیریت کند. برای کارهای معمول (محتوا، فروشگاه، rebrand) نیازی به phpMyAdmin یا FTP نیست.

---

## v1.0.0 — First Public Release

| Metric | Value |
|--------|-------|
| MCP tools | **~200** |
| Joomla | 4.x, 5.x, 6.x |
| Languages | en-GB, fa-IR |
| Auth | Bearer token |

### What AI can do without phpMyAdmin / SQL / files

| Task | MCP tools (no SQL needed) |
|------|---------------------------|
| Articles, categories, tags | `create_article`, `update_article`, `bulk_content_replace` |
| Menus & modules | `create_menu_item`, `update_module`, `assign_module_to_menu` |
| Site rebrand | `site_rebrand`, `search_site_content`, `update_global_config` |
| VirtueMart | `virtuemart_*` (products, prices, categories, vendor, config) |
| SP Page Builder | `save_sp_page`, `bulk_replace_sp_content`, `update_sp_page_meta` |
| Helix / templates | `update_helix_params`, `update_template_style`, `list_template_positions` |
| SEO | `update_article_seo_meta`, `create_joomla_redirect`, `update_schemaorg_for_item` |
| Multilingual | `update_content_language`, `set_article_associations` |

### Optional capabilities (off by default)

| Capability | Enables | Default |
|------------|---------|---------|
| `allow_file_read` | `read_file`, `list_directory` | On |
| `allow_file_write` | `upload_media`, `write_file`, template overrides | **Off** |
| `allow_sql_exec` | `execute_sql`, `list_db_tables` | **Off** |
| `allow_cli_exec` | `run_cache_clean`, `finder_rebuild_index` | **Off** |
| `allow_php_exec` | `execute_php` | **Off** |

**Recommended production policy:** keep SQL and PHP off; enable `allow_cli_exec` only if you need cache/finder from AI; enable `allow_file_write` only when AI should upload media or install extensions.

---

## Quick Start

### 1. Install

1. Download **`com_jmcp.zip`** from [Releases](https://github.com/mostafaafrouzi/JMCP/releases)
2. Joomla Admin → **System → Install → Extensions**
3. Upload and install

### 2. Configure

1. **Components → JMCP Dashboard → Options**
2. Copy the **Bearer Token** (auto-generated on install)
3. Enable only the capabilities you need
4. Optionally enable **Pro** features via `plg_system_jmcppro` (extras folder)

### 3. Connect AI

**HTTP endpoint:**

```
https://your-site.com/index.php?option=com_jmcp&task=rpc.handle
```

**Cursor / HTTP clients** — header:

```
Authorization: Bearer YOUR_JMCP_TOKEN
```

**Claude Desktop** — `mcp-http-bridge.js` (included in `site/`):

```json
{
  "mcpServers": {
    "joomla-mcp": {
      "command": "node",
      "args": [
        "https://your-site.com/components/com_jmcp/mcp-http-bridge.js",
        "https://your-site.com/index.php?option=com_jmcp&task=rpc.handle",
        "YOUR_JMCP_TOKEN"
      ]
    }
  }
}
```

### 4. First AI commands

Ask your AI to:

1. `discover_tools` — list all tools, skills, and policy flags
2. `get_site_info` — site metadata
3. `site_rebrand` with `dry_run: true` — preview a rebrand
4. `create_article` / `bulk_content_replace` — content operations

---

## Tool Categories (v1.0.0)

| Area | Examples |
|------|----------|
| **Core** | articles, categories, menus, modules, plugins, extensions |
| **Maintenance** | `bulk_content_replace`, `search_site_content`, `site_rebrand` |
| **Media** | list/upload/update (needs `allow_file_write` for upload) |
| **SEO** | analyze, meta, redirects, schema.org, finder |
| **Builders** | SP Page Builder (pages, collections, bulk replace) |
| **Helix** | layout, params, menu mega-layout |
| **Shops** | VirtueMart, HikaShop, J2Commerce |
| **Integrations** | Akeeba, RSForm, sh404SEF, JCE, AcyMailing |
| **Platform** | pending changes, webhooks, audit log, metrics |
| **Users & ops** | users, banners, scheduler, component params |

Run `discover_tools` after install for the full live list on your site.

---

## Security

- Bearer token authentication
- Per-tool Abilities Hub (enable/disable individual tools)
- Domain lock option
- HTML sanitization on article writes
- Policy gates for file/SQL/PHP/CLI
- Audit log of MCP operations
- Production warning for destructive tools

---

## Development

```bash
./build.sh   # produces com_jmcp.zip
```

### Test tools locally

```powershell
cd tools
.\mcp-session.ps1 -Tool "discover_tools" -ToolArgs @{}
.\test-all-tools.ps1
```

---

## Documentation

- [ROADMAP.md](ROADMAP.md) — development phases and status
- [LICENSE](LICENSE) — GPL-2.0-or-later

---

## Author

[JMCP Team](https://github.com/mostafaafrouzi/JMCP) — Issues and contributions welcome.
