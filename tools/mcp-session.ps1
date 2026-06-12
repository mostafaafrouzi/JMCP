param(
    [string]$Tool,
    [hashtable]$ToolArgs = @{}
)
$token = "46d05d23e16404f3a3d14a8e597c1c48f454d021a3c489b46b09f5f76e76d24e"
$uri = "http://localhost/autoserviceali/index.php?option=com_jmcp&task=rpc.handle"
$payload = @{ jsonrpc = "2.0"; id = 1; method = "tools/call"; params = @{ name = $Tool; arguments = $ToolArgs } } | ConvertTo-Json -Depth 20 -Compress
$r = Invoke-RestMethod -Uri $uri -Method POST -Headers @{ Authorization = "Bearer $token"; "Content-Type" = "application/json" } -Body $payload -TimeoutSec 180
if ($r.error) { Write-Output ("ERROR: " + ($r.error | ConvertTo-Json -Compress)); exit 1 }
if ($r.result.isError) { Write-Output $r.result.content[0].text; exit 2 }
Write-Output $r.result.content[0].text
