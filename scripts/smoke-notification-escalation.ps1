param(
    [string]$BaseUrl = "http://127.0.0.1:8080"
)

$ErrorActionPreference = "Stop"

function Ok($message) {
    Write-Host $message -ForegroundColor Green
}

function Fail($message) {
    throw $message
}

function AsJson($data) {
    return ($data | ConvertTo-Json -Depth 20)
}

Write-Host "Checking Re-born Step 23 Notification Center API at $BaseUrl" -ForegroundColor Cyan

$health = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/health"
if (-not $health.success -or $health.status -ne "ok") { Fail "Health check failed." }
if ($health.capabilities -notcontains "notification_center") { Fail "Health capabilities missing notification center." }
if ($health.capabilities -notcontains "notification_dispatch") { Fail "Health capabilities missing notification dispatch." }
if ($health.capabilities -notcontains "escalation_runs") { Fail "Health capabilities missing escalation runs." }
Ok "Health capabilities: ok"

$loginBody = AsJson @{ email = "admin@reborn.local"; password = "password" }
$login = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/auth/login" -ContentType "application/json" -Body $loginBody
if (-not $login.success -or -not $login.token.access_token) { Fail "Admin login failed." }
$headers = @{ Authorization = "Bearer $($login.token.access_token)" }
Ok "Login admin: ok"

$center = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/notification-center" -Headers $headers
if (-not $center.success -or -not $center.notification_center) { Fail "Notification center dashboard failed." }
if (-not $center.notification_center.channels) { Fail "Notification channels missing from dashboard." }
Ok "Notification center dashboard: ok"

$channels = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/notification-channels" -Headers $headers
if (-not $channels.success -or (($channels.notification_channels | Measure-Object).Count -lt 1)) { Fail "Notification channels endpoint failed." }
Ok "Notification channels: ok"

$channelBody = AsJson @{
    name = "Smoke test webhook"
    type = "webhook"
    target = "https://example.invalid/reborn-smoke-webhook"
    status = "active"
    config = @{ mock = $true; purpose = "Step 23 smoke test" }
}
$channel = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/notification-channels" -Headers $headers -ContentType "application/json" -Body $channelBody
if (-not $channel.success -or -not $channel.notification_channel.id) { Fail "Notification channel creation failed." }
Ok "Notification channel created: ok"

$rules = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/notification-rules" -Headers $headers
if (-not $rules.success -or (($rules.notification_rules | Measure-Object).Count -lt 1)) { Fail "Notification rules endpoint failed." }
Ok "Notification rules: ok"

$incidentBody = AsJson @{
    title = "Step 23 smoke escalation incident"
    severity = "high"
    summary = "Smoke test creates an incident so notification dispatch and escalation can be validated."
    impact = "No real impact; local pilot workflow validation."
}
$incident = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents" -Headers $headers -ContentType "application/json" -Body $incidentBody
if (-not $incident.success -or -not $incident.incident.id) { Fail "Incident creation failed." }
Ok "Incident created: $($incident.incident.id)"

$dispatchBody = AsJson @{ target_type = "incident"; target_id = $incident.incident.id }
$dispatch = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/notifications/dispatch" -Headers $headers -ContentType "application/json" -Body $dispatchBody
if (-not $dispatch.success -or $dispatch.notification_dispatch.created_count -lt 1) { Fail "Notification dispatch failed." }
$deliveryId = $dispatch.notification_dispatch.deliveries[0].id
Ok "Notification dispatch created: $($dispatch.notification_dispatch.created_count)"

$sentBody = AsJson @{ status = "sent"; message = "Smoke test marked mock delivery as sent." }
$sent = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/notification-deliveries/$deliveryId/status" -Headers $headers -ContentType "application/json" -Body $sentBody
if (-not $sent.success -or $sent.notification_delivery.status -ne "sent") { Fail "Delivery status update failed." }
Ok "Delivery marked sent: ok"

$deliveries = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/notification-deliveries?status=all&limit=20" -Headers $headers
if (-not $deliveries.success -or (($deliveries.notification_deliveries | Measure-Object).Count -lt 1)) { Fail "Notification deliveries listing failed." }
Ok "Notification deliveries listing: ok"

$policies = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/escalation-policies" -Headers $headers
if (-not $policies.success -or (($policies.escalation_policies | Measure-Object).Count -lt 1)) { Fail "Escalation policies endpoint failed." }
Ok "Escalation policies: ok"

$escalateBody = AsJson @{ note = "Step 23 smoke test escalation." }
$escalation = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents/$($incident.incident.id)/escalate" -Headers $headers -ContentType "application/json" -Body $escalateBody
if (-not $escalation.success -or -not $escalation.incident_escalation.escalation_run.id) { Fail "Incident escalation failed." }
Ok "Incident escalation started: ok"

$runs = Invoke-RestMethod -Method GET -Uri "$BaseUrl/api/v1/platform/escalation-runs?status=active" -Headers $headers
if (-not $runs.success -or (($runs.escalation_runs | Measure-Object).Count -lt 1)) { Fail "Escalation runs listing failed." }
Ok "Escalation runs listing: ok"

$resolveBody = AsJson @{
    status = "resolved"
    component = "platform"
    message = "Step 23 smoke test resolved the incident after escalation validation."
}
$resolved = Invoke-RestMethod -Method POST -Uri "$BaseUrl/api/v1/platform/incidents/$($incident.incident.id)/status" -Headers $headers -ContentType "application/json" -Body $resolveBody
if (-not $resolved.success -or $resolved.incident.status -ne "resolved") { Fail "Incident resolve failed." }
Ok "Incident resolved: ok"

Write-Host "Step 23 notification center and escalation smoke test passed." -ForegroundColor Green
