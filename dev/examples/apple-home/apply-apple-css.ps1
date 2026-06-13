# Upload Apple demo CSS and attach to an SP page via MCP (no file copy).
param([int]$PageId = 27)

$ErrorActionPreference = "Stop"
$Mcp = Join-Path $PSScriptRoot "..\..\mcp-client\mcp-session.ps1"
$CssFile = Join-Path $PSScriptRoot "apple-home.css"

function Invoke-Mcp([string]$Tool, [hashtable]$ToolArgs = @{}) {
    $out = & $Mcp -Tool $Tool -ToolArgs $ToolArgs
    if ($out -match '^ERROR:') { throw $out }
    if ($out -is [string] -and $out.TrimStart().StartsWith('{')) { return $out | ConvertFrom-Json }
    return $out | ConvertFrom-Json
}

$css = [IO.File]::ReadAllText($CssFile)
Write-Host "CSS file: $($css.Length) bytes"

$useMedia = $false
try {
    Invoke-Mcp "create_media_folder" @{ path = "images/jmcp" } | Out-Null
    $bytes = [IO.File]::ReadAllBytes($CssFile)
    $b64 = [Convert]::ToBase64String($bytes)
    $up = Invoke-Mcp "upload_media" @{ path = "images/jmcp"; filename = "apple-home.css"; content_base64 = $b64 }
    Write-Host "Uploaded: $($up.path)"
    $result = Invoke-Mcp "sp_set_page_css" @{ page_id = $PageId; media_path = "images/jmcp/apple-home.css" }
    $useMedia = $true
} catch {
    Write-Host "Media upload skipped ($($_.Exception.Message)); using inline css..." -ForegroundColor Yellow
    $result = Invoke-Mcp "sp_set_page_css" @{ page_id = $PageId; css = $css }
}

Write-Host "CSS saved: $($result.bytes) bytes via $($result.method)$(if ($useMedia) { ' (media_path)' } else { ' (inline)' })"
$preview = Invoke-Mcp "sp_preview_page" @{ page_id = $PageId }
Write-Host "Preview: $($preview.preview_url)"
exit 0
