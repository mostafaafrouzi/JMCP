# Site improvements ONLY via JMCP RPC (ASCII script; UTF-8 payloads in mcp-payloads/)
$ErrorActionPreference = "Continue"
$replacements = Get-Content "$PSScriptRoot\mcp-payloads\replacements.json" -Raw -Encoding UTF8 | ConvertFrom-Json
$log = @()

function Mcp([string]$Tool, [hashtable]$ToolArgs = @{}) {
    $r = & "$PSScriptRoot\mcp-session.ps1" -Tool $Tool -ToolArgs $ToolArgs 2>&1 | Out-String
    $ok = -not ($r -match '"success":\s*false' -or $r -match '^ERROR:')
    return @{ ok = $ok; text = $r.Trim() }
}

function Step([string]$label, [scriptblock]$action) {
    Write-Host "`n>> $label"
    $res = & $action
    $detail = if ($res.text.Length -gt 300) { $res.text.Substring(0, 300) } else { $res.text }
    $script:log += [pscustomobject]@{ step = $label; ok = $res.ok; detail = $detail }
    if (-not $res.ok) {
        Write-Host "[GAP] $label" -ForegroundColor Yellow
        Write-Host $res.text.Substring(0, [Math]::Min(500, $res.text.Length))
    } else { Write-Host "[OK]" -ForegroundColor Green }
}

Write-Host "=== JMCP Site Fix (MCP only) ==="

$langs = (Mcp "list_content_languages").text | ConvertFrom-Json
$fa = $langs.languages | Where-Object { $_.lang_code -eq "fa-IR" } | Select-Object -First 1
if ($fa) {
    Step "Publish fa-IR" { Mcp "update_content_language" @{ id = [int]$fa.lang_id; fields = @{ published = 1; ordering = 1 } } }
}

Step "Set site language fa-IR" { Mcp "update_global_config" @{ fields = @{ language = "fa-IR" } } }

Step "RTL template style" {
    $tpl = Get-Content "$PSScriptRoot\mcp-payloads\template-style-13.json" -Raw -Encoding UTF8 | ConvertFrom-Json -AsHashtable
    Mcp "update_template_style" $tpl
}

Step "get_helix_layout" { Mcp "get_helix_layout" @{ style_id = 13 } }

foreach ($id in 12..18) {
    Step "Unpublish SP $id" { Mcp "update_sp_page_meta" @{ id = $id; published = 0 } }
}

$repArr = @($replacements | ForEach-Object { @{ from = $_.from; to = $_.to } })
Step "bulk_replace_sp_content" { Mcp "bulk_replace_sp_content" @{ replacements = $repArr } }
Step "bulk_content_replace" { Mcp "bulk_content_replace" @{ presets = @("modules", "menus", "template_styles", "articles"); replacements = $repArr } }

Step "site_rebrand" {
    $rb = Get-Content "$PSScriptRoot\mcp-payloads\site-rebrand.json" -Raw -Encoding UTF8 | ConvertFrom-Json -AsHashtable
    Mcp "site_rebrand" $rb
}

Step "finder_rebuild_index" { Mcp "finder_rebuild_index" }
Step "run_cache_clean" { Mcp "run_cache_clean" }
Step "verify lorem" { Mcp "search_site_content" @{ needle = "lorem"; presets = @("sp_pages"); limit_per_column = 5 } }

Write-Host "`n=== SUMMARY ==="
($log | Where-Object { $_.ok }).Count
"ok"
($log | Where-Object { -not $_.ok }).Count
"failed"
$log | Where-Object { -not $_.ok } | ForEach-Object { Write-Host "FAIL: $($_.step) => $($_.detail)" }
