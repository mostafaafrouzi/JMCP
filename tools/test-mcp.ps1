# JMCP comprehensive MCP tool tester (local dev)
param(
    [string]$BaseUrl = "http://localhost/autoserviceali/index.php?option=com_jmcp&task=rpc.handle",
    [string]$Token = "46d05d23e16404f3a3d14a8e597c1c48f454d021a3c489b46b09f5f76e76d24e"
)

$ErrorActionPreference = "Continue"
$script:id = 0
$results = @()

function Invoke-Mcp {
    param([string]$Method, [hashtable]$Params = @{}, [string]$ToolName = "")
    $script:id++
    $body = @{ jsonrpc = "2.0"; id = $script:id }
    if ($ToolName) {
        $body.method = "tools/call"
        $body.params = @{ name = $ToolName; arguments = $Params }
    } else {
        $body.method = $Method
        if ($Params.Count -gt 0) { $body.params = $Params }
    }
    $tmp = [System.IO.Path]::GetTempFileName()
    $body | ConvertTo-Json -Depth 10 -Compress | Set-Content -Path $tmp -Encoding UTF8 -NoNewline
    $raw = curl.exe -s -X POST $BaseUrl `
        -H "Content-Type: application/json" `
        -H "X-JMCP-Token: $Token" `
        --data-binary "@$tmp"
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    try { return $raw | ConvertFrom-Json } catch { return @{ error = @{ message = $raw } } }
}

function Test-Tool {
    param([string]$Name, [hashtable]$Args = @{})
    $r = Invoke-Mcp -ToolName $Name -Params $Args
    $ok = $null -ne $r.result -and -not $r.result.isError
    $err = if ($r.error) { $r.error.message } elseif ($r.result.isError) { ($r.result.content[0].text | ConvertFrom-Json).error } else { "" }
    $script:results += [pscustomobject]@{ tool = $Name; ok = $ok; error = $err }
    $status = if ($ok) { "OK" } else { "FAIL" }
    Write-Host ("[{0}] {1}" -f $status, $Name)
    if (-not $ok -and $err) { Write-Host "       -> $err" }
    return $r
}

Write-Host "=== JMCP MCP Test Suite ===" -ForegroundColor Cyan

# Handshake
$init = Invoke-Mcp -Method "initialize" -Params @{
    protocolVersion = "2024-11-05"
    capabilities = @{}
    clientInfo = @{ name = "test-suite"; version = "1.0" }
}
if (-not $init.result) { Write-Error "Initialize failed: $($init | ConvertTo-Json)"; exit 1 }
Write-Host "Initialize: OK ($($init.result.serverInfo.name) v$($init.result.serverInfo.version))" -ForegroundColor Green

Invoke-Mcp -Method "notifications/initialized" | Out-Null

# List tools
$list = Invoke-Mcp -Method "tools/list"
$tools = $list.result.tools
Write-Host "Tools available: $($tools.Count)" -ForegroundColor Cyan

# Core read tools
$coreRead = @(
    "get_site_info", "discover_tools", "list_extensions", "list_articles",
    "list_categories", "list_menus", "list_menu_items", "list_modules",
    "list_plugins", "list_tags", "list_media", "list_db_tables",
    "list_installed_templates", "list_template_styles", "list_contacts",
    "list_custom_fields", "list_content_languages", "get_site_health_extended",
    "analyze_page_seo", "detect_installed_shops", "list_sp_pages",
    "virtuemart_list_products", "hikashop_list_products", "j2commerce_list_products"
)
foreach ($t in $coreRead) { Test-Tool $t @{} | Out-Null }

# Shop integrations on this site
Test-Tool "virtuemart_list_orders" @{ limit = 5 } | Out-Null
Test-Tool "list_sp_pages" @{ limit = 5 } | Out-Null

# Dry-run write
Test-Tool "create_article" @{ title = "JMCP Test"; catid = 2; introtext = "test"; dry_run = $true } | Out-Null

# Filesystem read (enabled)
Test-Tool "list_directory" @{ path = "templates" } | Out-Null
Test-Tool "read_file" @{ path = "configuration.php" } | Out-Null

# Disabled capabilities should return policy error (not crash)
$disabled = @("execute_sql", "execute_php", "run_cli_command", "write_file", "delete_file")
foreach ($t in $disabled) {
    $args = switch ($t) {
        "execute_sql" { @{ query = "SELECT 1" } }
        "execute_php" { @{ code = "return 1;" } }
        "run_cli_command" { @{ command = "cache:clean" } }
        "write_file" { @{ path = "tmp/jmcp-test.txt"; content = "x" } }
        "delete_file" { @{ path = "tmp/jmcp-test.txt" } }
    }
    Test-Tool $t $args | Out-Null
}

# Pro tools (should block without license)
foreach ($t in @("memory_store", "memory_list", "memory_search")) {
    Test-Tool $t @{ key = "test"; value = "v" } | Out-Null
}

# Resources / prompts if exposed
foreach ($m in @("resources/list", "prompts/list")) {
    $r = Invoke-Mcp -Method $m
    $ok = $null -ne $r.result
    Write-Host ("[{0}] {1}" -f $(if ($ok) {"OK"} else {"FAIL"}), $m)
}

$passed = ($results | Where-Object { $_.ok }).Count
$failed = ($results | Where-Object { -not $_.ok }).Count
Write-Host "`n=== Summary: $passed passed, $failed failed / $($results.Count) tested ===" -ForegroundColor $(if ($failed -eq 0) {"Green"} else {"Yellow"})

if ($failed -gt 0) {
    Write-Host "`nFailed tools:" -ForegroundColor Yellow
    $results | Where-Object { -not $_.ok } | ForEach-Object { Write-Host "  - $($_.tool): $($_.error)" }
}
