$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_E2E_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

function Invoke-MercatoApi {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [string]$Method = "GET",
        [object]$Body = $null
    )

    $uri = "$baseUrl/?rest_route=/mercato/v1$Path"
    if ($Body -eq $null) {
        return Invoke-RestMethod -Uri $uri -Method $Method
    }

    return Invoke-RestMethod -Uri $uri -Method $Method -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 10)
}

$cid = (docker ps --filter name=mercato-wordpress --format "{{.ID}}" | Select-Object -First 1)
if (!$cid) {
    throw "Mercato WordPress container is not running."
}

$network = (docker inspect $cid --format "{{range `$name, `$_ := .NetworkSettings.Networks}}{{`$name}}{{end}}")
$envArgs = @(
    "-e", "WORDPRESS_DB_HOST=mysql",
    "-e", "WORDPRESS_DB_NAME=mercato",
    "-e", "WORDPRESS_DB_USER=mercato",
    "-e", "WORDPRESS_DB_PASSWORD=mercato",
    "-e", "WORDPRESS_TABLE_PREFIX=wp_"
)

docker run --rm @envArgs --volumes-from $cid --network $network wordpress:cli wp plugin activate mercato-suite --path=/var/www/html | Out-Null

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

$mysql = (docker ps --filter name=mercato-mysql --format "{{.ID}}" | Select-Object -First 1)
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
$reconciliation = Invoke-MercatoApi -Path "/payouts/reconciliation" -Method "POST" -Body @{}
$report = Invoke-MercatoApi -Path "/reports/dashboard"
$export = Invoke-MercatoApi -Path "/reports/export" -Method "POST" -Body @{ report_type = "dashboard" }

$summary = docker exec $mysql mysql -umercato -pmercato -D mercato -e "SELECT status vendor_status FROM wp_mercato_vendors WHERE vendor_id=$($vendor.vendor_id); SELECT status kyc_status FROM wp_mercato_kyc_cases WHERE case_id=$($kyc.case_id); SELECT COUNT(*) clean_media FROM wp_mercato_media WHERE media_id=$($media.media_id) AND scan_status='clean'; SELECT COUNT(*) stripe_transfers FROM wp_mercato_stripe_transfers WHERE batch_id=$($batch.batch_id); SELECT status FROM wp_mercato_payout_batches WHERE batch_id=$($batch.batch_id); SELECT status,drift_minor FROM wp_mercato_reconciliation_runs WHERE run_id=$($reconciliation.run_id); SELECT status FROM wp_mercato_notification_deliveries WHERE delivery_id=$($delivery.delivery_id);"

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
    payout_batch = $batch
    stripe_execute = $stripeExecute
    delivery_id = $delivery.delivery_id
    reconciliation = $reconciliation
    report_gmv_minor = $report.gmv_minor
    export_id = $export.export_id
    database_summary = $summary
}

if ($mediaDone.scan_status -ne "clean") { throw "Media scan did not complete cleanly." }
if ([int]$stripeExecute.created -lt 1) { throw "Stripe transfer was not created." }
if ($reconciliation.status -ne "passed") { throw "Reconciliation did not pass." }
if (!$delivery.delivery_id) { throw "SendGrid delivery was not created." }
if (($summary -join "`n") -notmatch "approved") { throw "KYC did not approve the vendor." }

$result | ConvertTo-Json -Depth 10
