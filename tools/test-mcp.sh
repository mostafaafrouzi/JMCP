#!/bin/bash
set -euo pipefail

BASE_URL="${JMCP_URL:-http://localhost/autoserviceali/index.php?option=com_jmcp&task=rpc.handle}"
TOKEN="${JMCP_TOKEN:-46d05d23e16404f3a3d14a8e597c1c48f454d021a3c489b46b09f5f76e76d24e}"
TMPDIR="${TMPDIR:-/tmp}"
ID=0
PASS=0
FAIL=0

mcp() {
  local body="$1"
  curl.exe -s -X POST "$BASE_URL" \
    -H "Content-Type: application/json" \
    -H "X-JMCP-Token: $TOKEN" \
    -d "$body"
}

tool() {
  local name="$1"
  local args="${2:-\{\}}"
  ID=$((ID + 1))
  local script_dir
  script_dir="$(cd "$(dirname "$0")" && pwd)"
  local tmp="$script_dir/.mcp-req.json"
  printf '{"jsonrpc":"2.0","id":%d,"method":"tools/call","params":{"name":"%s","arguments":%s}}' "$ID" "$name" "$args" > "$tmp"
  local win_tmp
  win_tmp=$(cygpath -w "$tmp" 2>/dev/null || echo "$tmp")
  local resp
  resp=$(curl.exe -s -X POST "$BASE_URL" -H "Content-Type: application/json" -H "X-JMCP-Token: $TOKEN" --data-binary "@$win_tmp")
  if echo "$resp" | grep -q '"isError":true'; then
    echo "[FAIL] $name"
    echo "       $(echo "$resp" | head -c 200)"
    FAIL=$((FAIL + 1))
  elif echo "$resp" | grep -q '"error"'; then
    echo "[FAIL] $name (rpc error)"
    echo "       $(echo "$resp" | head -c 200)"
    FAIL=$((FAIL + 1))
  else
    echo "[OK]   $name"
    PASS=$((PASS + 1))
  fi
}

echo "=== JMCP MCP Test Suite ==="

INIT='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}'
resp=$(mcp "$INIT")
echo "$resp" | grep -q '"protocolVersion"' && echo "Initialize: OK" || { echo "Initialize FAILED: $resp"; exit 1; }

mcp '{"jsonrpc":"2.0","method":"notifications/initialized"}' >/dev/null

LIST=$(mcp '{"jsonrpc":"2.0","id":2,"method":"tools/list"}')
count=$(echo "$LIST" | grep -o '"name"' | wc -l)
echo "Tools listed: $count"

# Core reads
for t in get_site_info discover_tools list_extensions list_articles list_categories \
  list_menus list_menu_items list_modules list_plugins list_tags list_media \
  list_db_tables list_installed_templates list_template_styles list_contacts \
  list_custom_fields list_content_languages get_site_health_extended \
  detect_installed_shops list_sp_pages virtuemart_list_products virtuemart_list_orders; do
  tool "$t" '{}'
done

tool create_article '{"title":"JMCP Test","catid":2,"introtext":"test","dry_run":true}'
tool list_directory '{"path":"templates"}'
tool read_file '{"path":"configuration.php"}'

# Disabled caps (expect graceful failure, not crash)
for pair in \
  'execute_sql|{"query":"SELECT 1"}' \
  'execute_php|{"code":"return 1;"}' \
  'write_file|{"path":"tmp/x.txt","content":"x"}'; do
  name="${pair%%|*}"
  args="${pair#*|}"
  tool "$name" "$args"
done

# Pro gated
for t in memory_store memory_list; do
  tool "$t" '{"key":"k","value":"v"}'
done

echo ""
echo "=== Summary: $PASS passed, $FAIL failed ==="
