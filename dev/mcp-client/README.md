# MCP HTTP client

Minimal PowerShell helper for local JMCP testing. **Not** included in `com_jmcp.zip`.

```powershell
$env:JMCP_TOKEN = "your-bearer-token"
$env:JMCP_URL   = "http://localhost/yoursite/index.php?option=com_jmcp&task=rpc.handle"

.\mcp-session.ps1 -Tool "discover_tools" -ToolArgs @{}
.\mcp-session.ps1 -Tool "sp_validate_page" -ToolArgs @{ page_id = 27 }
```

Set `JMCP_TOKEN` and `JMCP_URL` in your environment instead of hardcoding tokens in scripts.
