# JMCP — Local development (not part of the extension)

This folder is **not** packaged into `com_jmcp.zip`. The Joomla component lives in `admin/` and `site/` only (`./build.sh`).

| Path | Purpose |
|------|---------|
| `mcp-client/` | Thin HTTP client for calling JMCP `tools/call` during local testing |
| `examples/` | Sample assets and one-off demos (e.g. Apple home CSS) |
| `scripts/legacy/` | Archived PowerShell experiments from early SP Page Builder trials — **do not use for production workflows** |

Use MCP tools on the server for all page building. PowerShell here only invokes the API.
