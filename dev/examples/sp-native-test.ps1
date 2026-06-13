# Verify native SP page build (no sp_repair_page_layout). Uses MCP designer tools only.
param(
    [string]$Title = "MCP Native Test"
)

$ErrorActionPreference = "Stop"
$Mcp = Join-Path $PSScriptRoot "..\mcp-client\mcp-session.ps1"

function Invoke-Mcp([string]$Tool, [hashtable]$ToolArgs = @{}) {
    & $Mcp -Tool $Tool -ToolArgs $ToolArgs | ConvertFrom-Json
}

Write-Host "=== SP native test page ===" -ForegroundColor Cyan

$created = Invoke-Mcp "save_sp_page" @{ title = $Title; content = "[]"; layout = "[]"; published = 0; language = "en-GB" }
$pageId = [int]$created.id
Write-Host "Created page ID: $pageId"

$row = Invoke-Mcp "sp_add_row" @{ page_id = $pageId; layout = "6.0+6.0" }
$col0 = $row.path -replace '\]$', '].columns[0]'
$col1 = $row.path -replace '\]$', '].columns[1]'

Invoke-Mcp "sp_add_addon" @{ page_id = $pageId; column_path = $col0; addon_name = "heading"; fields = @{ title = "Left column"; alignment = "center" } } | Out-Null
Invoke-Mcp "sp_add_addon" @{ page_id = $pageId; column_path = $col0; addon_name = "text_block"; fields = @{ text = "<p>Built via MCP without repair.</p>"; title = "" } } | Out-Null
Invoke-Mcp "sp_add_addon" @{ page_id = $pageId; column_path = $col1; addon_name = "heading"; fields = @{ title = "Right column"; alignment = "center" } } | Out-Null
Invoke-Mcp "sp_add_addon" @{ page_id = $pageId; column_path = $col1; addon_name = "text_block"; fields = @{ text = "<p>Column width should be 50%.</p>"; title = "" } } | Out-Null

$colNode = Invoke-Mcp "sp_get_node" @{ page_id = $pageId; path = "rows[0].columns[0]" }
$width = $colNode.node.settings.width.xl
Write-Host "Column 0 width.xl: $width" -ForegroundColor $(if ($width -eq "50%") { "Green" } else { "Red" })

$valid = Invoke-Mcp "sp_validate_page" @{ page_id = $pageId }
Invoke-Mcp "sp_save_page_design" @{ page_id = $pageId } | Out-Null
$preview = Invoke-Mcp "sp_preview_page" @{ page_id = $pageId }

Write-Host "Valid: $($valid.valid) | Addons: $($valid.addon_count)"
Write-Host "Admin: $($preview.admin_url)"
if ($width -ne "50%") { exit 1 }
