$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_E2E_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

function Invoke-MercatoApi {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [string]$Method = "GET",
        [object]$Body = $null,
        [hashtable]$ExtraHeaders = @{}
    )

    $uri = "$baseUrl/?rest_route=/mercato/v1$Path"
    $headers = @{
        "X-Mercato-Test-Secret" = $(if ($env:MERCATO_TEST_API_SECRET) { $env:MERCATO_TEST_API_SECRET } else { "mercato-local-test-secret" })
    }
    foreach ($key in $ExtraHeaders.Keys) {
        $headers[$key] = $ExtraHeaders[$key]
    }
    if ($Body -eq $null) {
        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers
    }

    return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 10)
}

$cid = (docker ps --filter name=mercato-wordpress --format "{{.ID}}" | Select-Object -First 1)
if (!$cid) {
    throw "Mercato WordPress container is not running."
}

$unauthorizedStatus = 0
try {
    Invoke-WebRequest -Uri "$baseUrl/?rest_route=/mercato/v1/products" -Method Post -ContentType "application/json" -Body "{}" -UseBasicParsing | Out-Null
} catch {
    $unauthorizedStatus = [int]$_.Exception.Response.StatusCode
}
if ($unauthorizedStatus -lt 400) {
    throw "Protected product creation route did not reject an unauthenticated request."
}

$live = Invoke-RestMethod -Uri "$baseUrl/?rest_route=/mercato/v1/health/live" -Method GET
if ($live.status -ne "ok") {
    throw "Liveness endpoint failed."
}

$network = (docker inspect $cid --format "{{range `$name, `$_ := .NetworkSettings.Networks}}{{`$name}}{{end}}")
$envArgs = @(
    "-e", "WORDPRESS_DB_HOST=mysql",
    "-e", "WORDPRESS_DB_NAME=mercato",
    "-e", "WORDPRESS_DB_USER=mercato",
    "-e", "WORDPRESS_DB_PASSWORD=mercato",
    "-e", "WORDPRESS_TABLE_PREFIX=wp_"
)

docker run --rm @envArgs --volumes-from $cid --network $network wordpress:cli wp plugin deactivate mercato-suite --path=/var/www/html 2>$null | Out-Null
docker run --rm @envArgs --volumes-from $cid --network $network wordpress:cli wp plugin activate mercato-suite --path=/var/www/html | Out-Null

$mysql = (docker ps --filter name=mercato-mysql --format "{{.ID}}" | Select-Object -First 1)
if (!$mysql) {
    throw "Mercato MySQL container is not running."
}
$outboxStartedAt = (docker exec $mysql mysql -umercato -pmercato --batch --skip-column-names -D mercato -e "SELECT UTC_TIMESTAMP(3);" | Select-Object -Last 1).Trim()

$rand = Get-Random
$vendor = Invoke-MercatoApi -Path "/vendors" -Method "POST" -Body @{
    business_name = "Smoke Vendor $rand"
    store_slug = "smoke-$rand"
    return_policy = "Returns accepted."
}

$stripeAccount = Invoke-MercatoApi -Path "/stripe/vendors/$($vendor.vendor_id)/account" -Method "POST" -Body @{
    email = "smoke-vendor@example.com"
}

Invoke-MercatoApi -Path "/stripe/webhook" -Method "POST" -Body @{
    id = "evt_smoke_$rand"
    type = "account.updated"
    data = @{
        object = @{
            id = $stripeAccount.stripe_account_id
            charges_enabled = $true
            payouts_enabled = $true
            details_submitted = $true
        }
    }
} | Out-Null

$kyc = Invoke-MercatoApi -Path "/kyc/$($vendor.vendor_id)/start" -Method "POST" -Body @{}
Invoke-MercatoApi -Path "/kyc/stripe/webhook" -Method "POST" -Body @{
    id = "evt_kyc_smoke_$rand"
    type = "identity.verification_session.verified"
    data = @{
        object = @{
            id = $kyc.provider_reference
        }
    }
} | Out-Null

$thread = Invoke-MercatoApi -Path "/messages/threads" -Method "POST" -Body @{
    vendor_id = [int]$vendor.vendor_id
    subject = "Smoke question"
    body = "Smoke body"
    sender_type = "buyer"
}

$product = Invoke-MercatoApi -Path "/products" -Method "POST" -Body @{
    vendor_id = [int]$vendor.vendor_id
    title = "Smoke Product $rand"
    description = "Smoke product."
    sku = "SMOKE-$rand"
    price_minor = 6400
    stock_quantity = 3
    status = "active"
}

$media = Invoke-MercatoApi -Path "/media/presign" -Method "POST" -Body @{
    owner_type = "product"
    owner_id = [int]$product.product_id
    file_name = "smoke.txt"
    content_type = "text/plain"
    size_bytes = 10
}
Invoke-WebRequest -Uri $media.upload_url -Method Put -ContentType "text/plain" -Body "smoke file" -UseBasicParsing | Out-Null
$mediaDone = Invoke-MercatoApi -Path "/media/$($media.media_id)/complete" -Method "POST" -Body @{ scan_status = "clean" }

$productId = [int]$product.wc_product_id
$orderCode = @"
`$product = wc_get_product($productId);
`$order = wc_create_order();
`$order->add_product(`$product, 1);
`$order->calculate_totals();
`$order->save();
do_action('woocommerce_checkout_order_processed', `$order->get_id(), [], `$order);
echo `$order->get_id();
"@
$orderId = docker run --rm @envArgs --volumes-from $cid --network $network wordpress:cli wp eval $orderCode --path=/var/www/html

$paymentIntent = Invoke-MercatoApi -Path "/stripe/payment-intents" -Method "POST" -Body @{
    wc_order_id = [int]$orderId
    amount_minor = 6400
    currency = "USD"
}
$paidSuborders = Invoke-MercatoApi -Path "/orders/$orderId/payment-complete" -Method "POST" -Body @{}
$suborderId = [int]$paidSuborders[0].suborder_id
$stripeRefund = Invoke-MercatoApi -Path "/stripe/refunds" -Method "POST" -Body @{
    payment_intent_id = $paymentIntent.stripe_payment_intent_id
    amount_minor = 1600
}
$orderRefund = Invoke-MercatoApi -Path "/orders/suborders/$suborderId/refund" -Method "POST" -Body @{
    amount_minor = 1600
    stripe_refund_id = $stripeRefund.stripe_refund_id
    reason = "smoke_partial_refund"
}

docker exec $mysql mysql -umercato -pmercato -D mercato -e "UPDATE wp_mercato_commissions SET available_at=UTC_TIMESTAMP(3) WHERE status='pending';" | Out-Null

$payoutCode = @'
$tenant = new Mercato\Core\Tenant\Resolver();
$outbox = new Mercato\Core\Events\Outbox($tenant);
$ledger = new Mercato\Payouts\Ledger($tenant, $outbox);
echo wp_json_encode($ledger->triggerBatch());
'@
$batch = docker run --rm @envArgs --volumes-from $cid --network $network wordpress:cli wp eval $payoutCode --path=/var/www/html | ConvertFrom-Json
$stripeExecute = Invoke-MercatoApi -Path "/stripe/payout-batches/$($batch.batch_id)/execute" -Method "POST" -Body @{}
$delivery = Invoke-MercatoApi -Path "/sendgrid/send" -Method "POST" -Body @{
    recipient = "smoke@example.com"
    subject = "Mercato smoke"
    body = "Smoke notification."
}
$idemHeaders = @{ "Idempotency-Key" = "smoke-sendgrid-$rand" }
$idemFirst = Invoke-MercatoApi -Path "/sendgrid/send" -Method "POST" -Body @{
    recipient = "idempotent@example.com"
    subject = "Mercato idempotency"
    body = "Idempotency notification."
} -ExtraHeaders $idemHeaders
$idemSecond = Invoke-MercatoApi -Path "/sendgrid/send" -Method "POST" -Body @{
    recipient = "idempotent@example.com"
    subject = "Mercato idempotency"
    body = "Idempotency notification."
} -ExtraHeaders $idemHeaders
$reconciliation = Invoke-MercatoApi -Path "/payouts/reconciliation" -Method "POST" -Body @{}
$report = Invoke-MercatoApi -Path "/reports/dashboard"
$export = Invoke-MercatoApi -Path "/reports/export" -Method "POST" -Body @{ report_type = "dashboard" }
$readiness = Invoke-MercatoApi -Path "/health/readiness"

for ($i = 0; $i -lt 30; $i++) {
    $pendingOutbox = (docker exec $mysql mysql -umercato -pmercato --batch --skip-column-names -D mercato -e "SELECT COUNT(*) FROM wp_mercato_event_outbox WHERE status IN ('pending','publishing') AND created_at >= '$outboxStartedAt';" | Select-Object -Last 1).Trim()
    if ([int]$pendingOutbox -eq 0) {
        break
    }
    Start-Sleep -Seconds 2
}

$summary = docker exec $mysql mysql -umercato -pmercato -D mercato -e "SELECT status vendor_status FROM wp_mercato_vendors WHERE vendor_id=$($vendor.vendor_id); SELECT status kyc_status FROM wp_mercato_kyc_cases WHERE case_id=$($kyc.case_id); SELECT COUNT(*) clean_media FROM wp_mercato_media WHERE media_id=$($media.media_id) AND scan_status='clean'; SELECT COUNT(*) stripe_payment_intents FROM wp_mercato_stripe_payment_intents WHERE wc_order_id=$orderId; SELECT COUNT(*) stripe_refunds FROM wp_mercato_stripe_refunds WHERE wc_order_id=$orderId; SELECT COUNT(*) order_refunds FROM wp_mercato_refunds WHERE refund_id=$($orderRefund.refund_id); SELECT COUNT(*) commission_reversals FROM wp_mercato_commission_reversals WHERE refund_id=$($orderRefund.refund_id); SELECT payment_status,refunded_minor FROM wp_mercato_suborders WHERE suborder_id=$suborderId; SELECT COUNT(*) stripe_transfers FROM wp_mercato_stripe_transfers WHERE batch_id=$($batch.batch_id); SELECT status FROM wp_mercato_payout_batches WHERE batch_id=$($batch.batch_id); SELECT status,drift_minor FROM wp_mercato_reconciliation_runs WHERE run_id=$($reconciliation.run_id); SELECT status FROM wp_mercato_notification_deliveries WHERE delivery_id=$($delivery.delivery_id); SELECT COUNT(*) idempotency_rows FROM wp_mercato_idempotency WHERE idempotency_key='smoke-sendgrid-$rand'; SELECT COUNT(*) audit_rows FROM wp_mercato_audit_log WHERE action IN ('vendor.registered','stripe.account.created','media.upload.presigned','media.upload.completed','product.created','stripe.payment_intent.created','order.payment.completed','stripe.refund.created','order.refund.created','stripe.payout_batch.executed','notification.email.sent','payout.reconciled'); SELECT COUNT(*) outbox_published FROM wp_mercato_event_outbox WHERE status='published' AND created_at >= '$outboxStartedAt'; SELECT COUNT(*) outbox_pending FROM wp_mercato_event_outbox WHERE status IN ('pending','publishing') AND created_at >= '$outboxStartedAt'; SELECT COUNT(*) outbox_dlq FROM wp_mercato_event_outbox WHERE status='dlq' AND created_at >= '$outboxStartedAt';"

$result = [pscustomobject]@{
    vendor_id = $vendor.vendor_id
    stripe_account_id = $stripeAccount.stripe_account_id
    kyc_case_id = $kyc.case_id
    thread_id = $thread.thread_id
    product_id = $product.product_id
    wc_product_id = $product.wc_product_id
    media_id = $media.media_id
    media_scan = $mediaDone.scan_status
    order_id = $orderId
    suborder_id = $suborderId
    payment_intent_id = $paymentIntent.stripe_payment_intent_id
    stripe_refund_id = $stripeRefund.stripe_refund_id
    order_refund_id = $orderRefund.refund_id
    payout_batch = $batch
    stripe_execute = $stripeExecute
    delivery_id = $delivery.delivery_id
    idempotent_delivery_id = $idemSecond.delivery_id
    reconciliation = $reconciliation
    report_gmv_minor = $report.gmv_minor
    report_refunded_minor = $report.refunded_minor
    report_net_gmv_minor = $report.net_gmv_minor
    report_net_take_minor = $report.net_take_minor
    readiness_status = $readiness.status
    export_id = $export.export_id
    database_summary = $summary
}

if ($mediaDone.scan_status -ne "clean") { throw "Media scan did not complete cleanly." }
if ([int]$stripeExecute.created -lt 1) { throw "Stripe transfer was not created." }
if ($reconciliation.status -ne "passed") { throw "Reconciliation did not pass." }
if (!$delivery.delivery_id) { throw "SendGrid delivery was not created." }
if ($idemFirst.delivery_id -ne $idemSecond.delivery_id) { throw "Idempotency replay did not return the original SendGrid delivery." }
$joinedSummary = $summary -join "`n"
if ($joinedSummary -notmatch "approved") { throw "KYC did not approve the vendor." }
if ($joinedSummary -notmatch "stripe_payment_intents\s+1") { throw "Stripe PaymentIntent was not recorded." }
if ($joinedSummary -notmatch "stripe_refunds\s+1") { throw "Stripe refund was not recorded." }
if ($joinedSummary -notmatch "order_refunds\s+1") { throw "Order refund was not recorded." }
if ($joinedSummary -notmatch "commission_reversals\s+1") { throw "Commission reversal was not recorded." }
if ($joinedSummary -notmatch "partially_refunded\s+1600") { throw "Partial refund did not update suborder payment status." }
if ($joinedSummary -notmatch "idempotency_rows\s+1") { throw "Idempotency response was not stored." }
if ($joinedSummary -notmatch "audit_rows\s+[1-9][0-9]") { throw "Audit log did not capture expected production mutations." }
if ($joinedSummary -notmatch "outbox_published\s+[1-9][0-9]*") { throw "Outbox relay did not publish smoke events." }
if ($joinedSummary -notmatch "outbox_pending\s+0") { throw "Outbox relay left smoke events pending." }
if ($joinedSummary -notmatch "outbox_dlq\s+0") { throw "Outbox relay dead-lettered smoke events." }
if ([int]$report.refunded_minor -lt 1600) { throw "Dashboard did not include refund totals." }
if ([int]$report.net_take_minor -lt 1) { throw "Dashboard did not include net take." }
if ($readiness.status -ne "ok") { throw "Readiness endpoint did not return ok." }

$result | ConvertTo-Json -Depth 10
