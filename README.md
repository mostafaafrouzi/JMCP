# JMCP — Joomla Model Context Protocol Server

[![Joomla](https://img.shields.io/badge/Joomla-4.x%20%7C%205.x%20%7C%206.x-blue)](https://www.joomla.org)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)
[![Release](https://img.shields.io/github/v/release/mostafaafrouzi/JMCP?label=release)](../../releases)

**JMCP** is a native Joomla component that exposes a full [Model Context Protocol (MCP)](https://modelcontextprotocol.io) server. It lets AI assistants — Cursor, Claude Desktop, Claude Code, Antigravity, and others — manage a Joomla site like an experienced webmaster.

---

### [فارسی]

**JMCP** یک کامپوننت نیتیو جوملا است که سرور MCP را در خود سایت راه‌اندازی می‌کند. هوش مصنوعی می‌تواند مطالب، منوها، ماژول‌ها، پلاگین‌ها، قالب‌ها، فایل‌ها، دیتابیس و افزونه‌هایی مانند SP Page Builder، Helix Ultimate، VirtueMart و HikaShop را مدیریت کند.

---

## Features (v2.0.0 — 100+ MCP Tools)

| Phase | Capabilities |
|-------|-------------|
| **Phase 1 — Core** | Media manager, article versions, multilingual, templates/overrides, tags, custom fields, contacts |
| **Phase 2 — SEO** | SEO analysis, meta bulk update, sitemap, broken links, cache, health checks |
| **Phase 2 — Builders** | SP Page Builder advanced, Helix Ultimate params |
| **Phase 3 — Shops** | VirtueMart, HikaShop, J2Commerce product/order tools |
| **Phase 3 — Extensions** | Akeeba, Admin Tools, sh404SEF, JCE, RSForm, AcyMailing |
| **Phase 4 — Platform** | MCP Resources, Prompts/Skills, pending changes queue, webhooks, audit log |
| **Security** | Abilities Hub, domain lock, dry-run mode, HTML sanitization, per-tool disable |

See [ROADMAP.md](ROADMAP.md) for the full phased plan.

## Quick Start

### 1. Install

1. Download `com_jmcp.zip` from [Releases](../../releases)
2. Joomla Admin → **System → Install → Extensions**
3. Upload and install the ZIP

### 2. Configure

1. Go to **Components → JMCP Dashboard**
2. Open **Options** (toolbar)
3. Set your **Bearer Token** (auto-generated on first install)
4. Enable only the capabilities you need (file write, SQL, PHP are off by default)

### 3. Connect AI Clients

**Endpoint (HTTP / SSE):**
```
https://your-site.com/index.php?option=com_jmcp&task=rpc.handle
```

**Claude Desktop** — add to `claude_desktop_config.json`:
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

**Cursor / Claude Code** — use the SSE URL with header:
```
Authorization: Bearer YOUR_JMCP_TOKEN
```

Copy-paste configs are available in the admin dashboard.

## Development

```bash
./build.sh
```

Produces `com_jmcp.zip` ready for Joomla installation.

### Release

Push a version tag to trigger the GitHub Action:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The workflow builds the ZIP and creates a GitHub Release with changelog and downloadable package.

## Architecture

```
JMCP Component (com_jmcp)
├── Site endpoint     → HTTP JSON-RPC 2.0 + SSE transport
├── Admin dashboard   → Metrics, connection guides, options
├── Tool executors    → Joomla APIs + filesystem + SQL + PHP
└── mcp-http-bridge.js → stdio bridge for desktop clients
```

## Roadmap

- Per-tool policy hub (like NovaMira Abilities)
- Dedicated integrations: Helix Ultimate, VirtueMart, HikaShop, J2Commerce
- Pro tier for advanced integrations and persistent memory
- MCP registry distribution (Smithery / Glama)

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

## Author

[JMCP Team](https://github.com/mostafaafrouzi/JMCP)
