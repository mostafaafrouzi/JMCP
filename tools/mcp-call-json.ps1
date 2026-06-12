param([string]$Tool, [string]$JsonPath)
$args = Get-Content $JsonPath -Raw -Encoding UTF8 | ConvertFrom-Json -AsHashtable
& "$PSScriptRoot\mcp-session.ps1" -Tool $Tool -ToolArgs $args
