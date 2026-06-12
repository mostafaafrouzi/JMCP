# JMCP comprehensive tool smoke test (read-only / dry_run where possible)
$ErrorActionPreference = "Continue"
$token = $env:JMCP_TOKEN
if (-not $token) {
    $token = "46d05d23e16404f3a3d14a8e597c1c48f454d021a3c489b46b09f5f76e76d24e"
}
$base = $env:JMCP_URL
if (-not $base) {
    $base = "http://localhost/autoserviceali/index.php?option=com_jmcp&task=rpc.handle"
}
$reqFile = Join-Path $PSScriptRoot "req.json"

function Invoke-McpTool([string]$name, [hashtable]$toolArgs = @{}) {
    $id = Get-Random -Minimum 1000 -Maximum 999999
    $argJson = if ($toolArgs.Count -eq 0) { "{}" } else { ($toolArgs | ConvertTo-Json -Compress) }
    $body = "{`"jsonrpc`":`"2.0`",`"id`":$id,`"method`":`"tools/call`",`"params`":{`"name`":`"$name`",`"arguments`":$argJson}}"
    [System.IO.File]::WriteAllText($reqFile, $body)
    return curl.exe -s -X POST $base -H "Content-Type: application/json" -H "X-JMCP-Token: $token" --data-binary "@$reqFile"
}

# Fetch tool list
[System.IO.File]::WriteAllText($reqFile, '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}')
$listRaw = curl.exe -s -X POST $base -H "Content-Type: application/json" -H "X-JMCP-Token: $token" --data-binary "@$reqFile"
$list = ($listRaw | ConvertFrom-Json).result.tools | ForEach-Object { $_.name }

# Minimal args per tool (dry_run for writes)
$argMap = @{
    "get_article" = @{ id = 1 }
    "create_article" = @{ title = "MCP Test"; catid = 2; introtext = "x"; dry_run = $true }
    "update_article" = @{ id = 1; title = "x"; dry_run = $true }
    "delete_article" = @{ id = 999999; dry_run = $true }
    "get_category" = @{ id = 2 }
    "create_category" = @{ title = "T"; extension = "com_content"; dry_run = $true }
    "update_category" = @{ id = 2; title = "T"; dry_run = $true }
    "delete_category" = @{ id = 999999; dry_run = $true }
    "list_menu_items" = @{ menutype = "mainmenu" }
    "get_menu_item" = @{ id = 101 }
    "create_menu_item" = @{ title = "T"; menutype = "mainmenu"; type = "component"; dry_run = $true }
    "update_menu_item" = @{ id = 101; title = "T"; dry_run = $true }
    "delete_menu_item" = @{ id = 999999; dry_run = $true }
    "get_module" = @{ id = 1 }
    "create_module" = @{ title = "T"; module = "mod_custom"; position = "position-7"; dry_run = $true }
    "update_module" = @{ id = 1; title = "T"; dry_run = $true }
    "delete_module" = @{ id = 999999; dry_run = $true }
    "toggle_plugin_state" = @{ element = "jmcp"; folder = "system"; enabled = 1; dry_run = $true }
    "update_plugin_params" = @{ element = "jmcp"; folder = "system"; params = "{}"; dry_run = $true }
    "list_directory" = @{ path = "templates" }
    "read_file" = @{ path = "configuration.php" }
    "get_sp_page" = @{ id = 1 }
    "save_sp_page" = @{ id = 1; title = "T"; content = "[]"; dry_run = $true }
    "update_global_config" = @{ fields = @{ sitename = "Test" }; dry_run = $true }
    "bulk_content_replace" = @{ preset = "sp_pages"; replacements = @(@{ from = "ZZZNOTFOUND"; to = "x" }); dry_run = $true }
    "search_site_content" = @{ needle = "گیربکس"; preset = "sp_pages"; limit_per_column = 2 }
    "site_rebrand" = @{ brand = "Test Brand"; old_brand = "Old"; dry_run = $true }
    "bulk_replace_sp_content" = @{ replacements = @(@{ from = "ZZZNOTFOUND"; to = "x" }); dry_run = $true }
    "virtuemart_update_category" = @{ id = 2; name = "Test"; dry_run = $true }
    "virtuemart_update_product" = @{ id = 2004; name = "Test"; dry_run = $true }
    "virtuemart_update_vendor" = @{ name = "Test Store"; dry_run = $true }
    "virtuemart_clone_language_tables" = @{ source_suffix = "en_gb"; target_suffix = "fa_ir"; dry_run = $true }
    "virtuemart_list_products" = @{ limit = 3 }
    "get_media" = @{ path = "images" }
    "get_article_version" = @{ article_id = 1; version_id = 1 }
    "restore_article_version" = @{ article_id = 1; version_id = 1; dry_run = $true }
    "delete_article_version" = @{ article_id = 1; version_id = 1; dry_run = $true }
    "keep_article_version" = @{ article_id = 1; version_id = 1; dry_run = $true }
    "get_content_language" = @{ tag = "en-GB" }
    "create_content_language" = @{ title = "Test"; tag = "xx-XX"; dry_run = $true }
    "update_content_language" = @{ tag = "en-GB"; title = "English"; dry_run = $true }
    "set_article_associations" = @{ id = 1; associations = @{}; dry_run = $true }
    "set_menu_item_associations" = @{ id = 101; associations = @{}; dry_run = $true }
    "get_template_style" = @{ id = 1 }
    "create_template_style" = @{ title = "T"; template = "cassiopeia"; dry_run = $true }
    "update_template_style" = @{ id = 1; title = "T"; dry_run = $true }
    "delete_template_style" = @{ id = 999999; dry_run = $true }
    "create_tag" = @{ title = "T"; dry_run = $true }
    "update_tag" = @{ id = 1; title = "T"; dry_run = $true }
    "delete_tag" = @{ id = 999999; dry_run = $true }
    "get_custom_field" = @{ id = 1 }
    "create_custom_field" = @{ title = "T"; type = "text"; context = "com_content.article"; dry_run = $true }
    "update_field_values" = @{ item_id = 1; field_id = 1; value = "x"; dry_run = $true }
    "get_contact" = @{ id = 1 }
    "create_contact" = @{ name = "T"; catid = 2; dry_run = $true }
    "update_contact" = @{ id = 1; name = "T"; dry_run = $true }
    "delete_contact" = @{ id = 999999; dry_run = $true }
    "analyze_page_seo" = @{ url = "/" }
    "update_article_seo_meta" = @{ id = 1; metadesc = "x"; dry_run = $true }
    "suggest_internal_links" = @{ article_id = 1 }
    "publish_sp_page_to_menu" = @{ page_id = 1; menutype = "mainmenu"; dry_run = $true }
    "sh404sef_create_redirect" = @{ old_url = "/old"; new_url = "/new"; dry_run = $true }
    "create_pending_change" = @{ tool = "create_article"; payload = @{}; dry_run = $true }
    "approve_pending_change" = @{ id = 1; dry_run = $true }
    "reject_pending_change" = @{ id = 1; dry_run = $true }
    "virtuemart_set_product_price" = @{ product_id = 2004; price = 100000; dry_run = $true }
    "virtuemart_assign_product_categories" = @{ product_id = 2004; category_ids = @(2); mode = "add"; dry_run = $true }
    "virtuemart_manage_product_media" = @{ action = "list"; product_id = 2004 }
    "virtuemart_get_config" = @{}
    "virtuemart_set_config" = @{ config = @{ shop_is_offline = "0" }; dry_run = $true }
    "virtuemart_list_custom_fields" = @{ limit = 5 }
    "virtuemart_set_custom_field" = @{ title = "Test"; dry_run = $true }
    "delete_sp_page" = @{ id = 999999; dry_run = $true }
    "update_sp_page_meta" = @{ id = 1; og_title = "Test"; dry_run = $true }
    "list_sp_page_modules" = @{}
    "get_helix_menu_layout" = @{ menu_id = 101 }
    "update_helix_menu_layout" = @{ menu_id = 101; layout = @{}; dry_run = $true }
    "list_template_positions" = @{ template = "ut_resto" }
    "update_ut_articles_module" = @{ id = 112; count = 4; dry_run = $true }
    "assign_module_to_menu" = @{ module_id = 112; menu_ids = @(101); mode = "add" }
    "set_default_template_style" = @{ style_id = 13; dry_run = $true }
    "update_component_params" = @{ option = "com_content"; params = @{ show_feed_link = "1" }; dry_run = $true }
    "finder_rebuild_index" = @{}
    "create_banner" = @{ name = "Test Banner"; dry_run = $true }
    "update_banner" = @{ id = 1; fields = @{ name = "T" }; dry_run = $true }
    "delete_banner" = @{ id = 999999; dry_run = $true }
    "create_newsfeed" = @{ name = "Test"; link = "https://example.com/feed"; dry_run = $true }
    "update_newsfeed" = @{ id = 1; fields = @{ name = "T" }; dry_run = $true }
    "delete_newsfeed" = @{ id = 999999; dry_run = $true }
    "update_joomla_redirect" = @{ id = 1; fields = @{ comment = "x" }; dry_run = $true }
    "delete_joomla_redirect" = @{ id = 999999; dry_run = $true }
    "create_user" = @{ name = "Test"; username = "testuser999"; email = "test999@example.com"; dry_run = $true }
    "update_user" = @{ id = 42; fields = @{ name = "T" }; dry_run = $true }
    "assign_user_groups" = @{ user_id = 42; group_ids = @(2); mode = "add" }
    "toggle_extension" = @{ element = "com_finder"; enabled = $true; dry_run = $true }
    "list_scheduler_tasks" = @{}
    "run_scheduler_task" = @{}
    "get_schemaorg_for_item" = @{ item_id = 1 }
    "update_schemaorg_for_item" = @{ item_id = 1; schema = @{ "@type" = "Article" }; dry_run = $true }
    "update_custom_field" = @{ id = 1; fields = @{ label = "T" }; dry_run = $true }
    "delete_custom_field" = @{ id = 999999; dry_run = $true }
    "list_sp_collections" = @{}
    "get_sp_collection" = @{ id = 1 }
    "save_sp_collection" = @{ title = "Test"; dry_run = $true }
    "delete_sp_collection" = @{ id = 999999; dry_run = $true }
    "configure_webhook" = @{ url = "https://example.com/hook"; enabled = $false }
    "get_webhook_config" = @{}
    "create_site_snapshot" = @{ label = "test"; tables = @("#__content") }
    "restore_site_snapshot" = @{ path = "tmp/jmcp_snapshots/test.json"; dry_run = $true }
    "export_rsform_submissions" = @{ form_id = 1 }
    "install_extension" = @{ path = "nonexistent.zip"; dry_run = $true }
    "update_extension" = @{ path = "nonexistent.zip"; dry_run = $true }
    "apply_joomla_update" = @{}
}

$ok = @(); $policy = @(); $pro = @(); $err = @()

foreach ($tool in $list) {
    $toolArgs = if ($argMap.ContainsKey($tool)) { $argMap[$tool] } else { @{} }
    $r = Invoke-McpTool $tool $toolArgs
    if ($r -match '"isError":false') {
        $ok += $tool
    } elseif ($r -match 'COM_JMCP_ERR_CAPABILITY_DISABLED') {
        $policy += $tool
    } elseif ($r -match 'COM_JMCP_ERR_PRO_REQUIRED') {
        $pro += $tool
    } elseif ($r -match '"result"' -and $r -notmatch '"error"') {
        $ok += $tool
    } else {
        $msg = if ($r.Length -lt 200) { $r } else { $r.Substring(0, 200) }
        $err += "$tool => $msg"
    }
}

Write-Host "=== JMCP Tool Test Summary ==="
Write-Host "Total tools: $($list.Count)"
Write-Host "OK: $($ok.Count)"
Write-Host "Policy blocked: $($policy.Count)"
Write-Host "Pro required: $($pro.Count)"
Write-Host "Errors: $($err.Count)"
if ($err.Count -gt 0) {
    Write-Host "`n--- Errors ---"
    $err | ForEach-Object { Write-Host $_ }
}
