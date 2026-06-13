# Apply Apple demo CSS to an SP page via MCP

Uses `sp_set_page_css` (reads CSS from this folder). Optionally uploads with `upload_media` when `allow_file_write` is enabled in JMCP options.

```powershell
.\apply-apple-css.ps1 -PageId 27
```

Without `allow_file_write`, the script falls back to inline `css` (still MCP — no manual file copy).
