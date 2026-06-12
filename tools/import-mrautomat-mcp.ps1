# Import Mrautomat .txt articles to Joomla via JMCP RPC only
$ErrorActionPreference = "Stop"
$sourceDir = "C:\Users\mosta\OneDrive\Desktop\Word To HTML File\Mrautomat - Copy (2) - Copy"
$token = "46d05d23e16404f3a3d14a8e597c1c48f454d021a3c489b46b09f5f76e76d24e"
$uri = "http://localhost/autoserviceali/index.php?option=com_jmcp&task=rpc.handle"
$log = @()

function Invoke-Mcp {
    param([string]$Tool, [hashtable]$Arguments)
    $payload = @{
        jsonrpc = "2.0"
        id      = 1
        method  = "tools/call"
        params  = @{ name = $Tool; arguments = $Arguments }
    }
    $json = $payload | ConvertTo-Json -Depth 30 -Compress
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
    $resp = Invoke-RestMethod -Uri $uri -Method POST -Headers @{
        Authorization  = "Bearer $token"
        "Content-Type" = "application/json; charset=utf-8"
    } -Body $bytes -TimeoutSec 300
    if ($resp.error) { throw "MCP error: $($resp.error.message)" }
    if ($resp.result.isError) {
        $msg = $resp.result.content[0].text | ConvertFrom-Json
        throw "Tool failed: $($msg.error)"
    }
    return ($resp.result.content[0].text | ConvertFrom-Json)
}

function Get-CategoryId([string]$baseName) {
    if ($baseName -match 'oil-change|importance-of-gearbox') { return 10 }
    return 9
}

function Convert-Content([string]$raw, [string]$alias) {
    $html = $raw.Trim()
    $html = $html -replace '(?s)^<article[^>]*>', '<div dir="rtl" class="article-body">'
    $html = $html -replace '</article>\s*$', '</div>'
    $html = $html -replace 'مستر اتومات', 'اتو سرویس ستاره'
    $html = $html -replace 'مستراتومات', 'اتو سرویس ستاره'
    $html = $html -replace 'MrAutomat', 'اتو سرویس ستاره'
    $html = $html -replace 'mrautomat', 'setareh-auto'
    # internal article links -> Joomla aliases (filename-based)
    $html = $html -replace 'href="([a-z0-9-]+)"', {
        param($m)
        $slug = $m.Groups[1].Value
        if ($slug -match '^(https?:|#|/)') { return $m.Value }
        if ($slug -match 'automatic-gearbox') {
            $slug = $slug -replace '-automatic-gearbox-', '-'
            $slug = $slug -replace '-automatic-gearbox-repair', '-gearbox-repair'
        }
        'href="index.php?option=com_content&view=article&alias=' + $slug + '"'
    }
  return $html
}

function Get-Title([string]$html, [string]$baseName) {
    if ($html -match '<h2[^>]*>([^<]+)</h2>') {
        return $matches[1].Trim()
    }
    return $baseName -replace '-', ' '
}

$files = Get-ChildItem $sourceDir -Filter "*.txt" | Sort-Object Name
Write-Host "Importing $($files.Count) articles via MCP..."

foreach ($file in $files) {
    $alias = $file.BaseName
    $raw = [System.IO.File]::ReadAllText($file.FullName, [System.Text.Encoding]::UTF8)
    $title = Get-Title $raw $alias
    $body = Convert-Content $raw $alias
    $catid = Get-CategoryId $alias
    $status = "ok"
    $id = 0
    try {
        $existing = $null
        try {
            $existing = Invoke-Mcp "get_article_by_alias" @{ alias = $alias }
        } catch { }

        if ($existing -and $existing.id) {
            $id = [int]$existing.id
            Invoke-Mcp "update_article" @{
                id     = $id
                fields = @{
                    title     = $title
                    alias     = $alias
                    catid     = $catid
                    introtext = $body
                    fulltext  = ""
                    state     = 1
                    language  = "fa-IR"
                }
            } | Out-Null
            $action = "updated"
        } else {
            $created = Invoke-Mcp "create_article" @{
                title     = $title
                catid     = $catid
                introtext = $body
                fulltext  = ""
                state     = 1
                language  = "fa-IR"
            }
            $id = [int]$created.id
            Invoke-Mcp "update_article" @{
                id     = $id
                fields = @{ alias = $alias; title = $title }
            } | Out-Null
            $action = "created"
        }
        Write-Host "[OK] $action $alias (id=$id) - $title"
    } catch {
        $status = "fail"
        $action = $_.Exception.Message
        Write-Host "[FAIL] $alias - $action" -ForegroundColor Red
    }
    $log += [PSCustomObject]@{ alias = $alias; id = $id; action = $action; status = $status }
}

try {
    Invoke-Mcp "run_cache_clean" @{} | Out-Null
    Write-Host "Cache cleaned."
} catch {
    Write-Host "Cache clean skipped: $_"
}

$log | Export-Csv "$PSScriptRoot\import-mrautomat-log.csv" -NoTypeInformation -Encoding UTF8
Write-Host "Done. Log: tools/import-mrautomat-log.csv"
