$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_E2E_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

$secret = $env:MERCATO_TEST_API_SECRET
if (!$secret) {
    $secret = "mercato-local-test-secret"
}

function Invoke-MercatoApi {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [string]$Method = "GET",
        [object]$Body = $null
    )

    $headers = @{ "X-Mercato-Test-Secret" = $secret }
    $uri = "$baseUrl/?rest_route=/mercato/v1$Path"
    if ($Body -eq $null) {
        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers
    }

    return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 10)
}

$vendors = @(
    @{
        business_name = "Northstar Outfitters"
        store_slug = "northstar-outfitters"
        return_policy = "30-day returns on unopened outdoor gear."
        products = @(
            @{ title = "Trailhead Waxed Canvas Pack"; sku = "NS-PACK-001"; price_minor = 8900; stock_quantity = 18; description = "Weather-resistant day pack for commuter and trail use." },
            @{ title = "Summit Merino Base Layer"; sku = "NS-MERINO-002"; price_minor = 6400; stock_quantity = 24; description = "Soft merino layer designed for cold mornings and long routes." }
        )
    },
    @{
        business_name = "Verde Home Market"
        store_slug = "verde-home-market"
        return_policy = "Home goods ship plastic-free with 14-day returns."
        products = @(
            @{ title = "Olivewood Serving Board"; sku = "VH-BOARD-001"; price_minor = 4800; stock_quantity = 31; description = "Hand-finished board for table service and kitchen prep." },
            @{ title = "Linen Pantry Towel Set"; sku = "VH-LINEN-002"; price_minor = 3600; stock_quantity = 42; description = "Stonewashed linen towels in a three-piece neutral set." }
        )
    },
    @{
        business_name = "Atelier Saffron"
        store_slug = "atelier-saffron"
        return_policy = "Made-to-order accessories are inspected before dispatch."
        products = @(
            @{ title = "Saffron Leather Crossbody"; sku = "AS-BAG-001"; price_minor = 12800; stock_quantity = 9; description = "Compact leather crossbody with brass hardware and lined interior." },
            @{ title = "Woven Market Scarf"; sku = "AS-SCARF-002"; price_minor = 5400; stock_quantity = 16; description = "Lightweight woven scarf made for year-round layering." }
        )
    },
    @{
        business_name = "Circuit & Co"
        store_slug = "circuit-co"
        return_policy = "Electronics include tracked shipping and warranty support."
        products = @(
            @{ title = "Desk Dock Pro USB-C Hub"; sku = "CC-HUB-001"; price_minor = 7600; stock_quantity = 27; description = "Seven-port USB-C hub for hybrid workstations and creators." },
            @{ title = "Magnetic Cable Kit"; sku = "CC-CABLE-002"; price_minor = 2900; stock_quantity = 55; description = "Braided charging cable kit with compact magnetic organizer." }
        )
    }
)

$created = @()
foreach ($vendorSpec in $vendors) {
    $existing = @(Invoke-MercatoApi -Path "/vendors" | Where-Object { $_.store_slug -eq $vendorSpec.store_slug })
    if ($existing.Count -gt 0) {
        $vendor = $existing[0]
    } else {
        $vendor = Invoke-MercatoApi -Path "/vendors" -Method "POST" -Body @{
            business_name = $vendorSpec.business_name
            store_slug = $vendorSpec.store_slug
            return_policy = $vendorSpec.return_policy
        }
    }

    Invoke-MercatoApi -Path "/vendors/$($vendor.vendor_id)/status" -Method "POST" -Body @{ status = "approved" } | Out-Null
    $account = Invoke-MercatoApi -Path "/stripe/vendors/$($vendor.vendor_id)/account" -Method "POST" -Body @{ email = "$($vendorSpec.store_slug)@example.test" }
    Invoke-MercatoApi -Path "/stripe/webhook" -Method "POST" -Body @{
        id = "evt_demo_$($vendor.vendor_id)_$(Get-Random)"
        type = "account.updated"
        data = @{ object = @{ id = $account.stripe_account_id; charges_enabled = $true; payouts_enabled = $true; details_submitted = $true } }
    } | Out-Null

    $kyc = Invoke-MercatoApi -Path "/kyc/$($vendor.vendor_id)/start" -Method "POST" -Body @{}
    Invoke-MercatoApi -Path "/kyc/stripe/webhook" -Method "POST" -Body @{
        id = "evt_demo_kyc_$($vendor.vendor_id)_$(Get-Random)"
        type = "identity.verification_session.verified"
        data = @{ object = @{ id = $kyc.provider_reference } }
    } | Out-Null

    foreach ($productSpec in $vendorSpec.products) {
        $products = @(Invoke-MercatoApi -Path "/products" | Where-Object { $_.sku -eq $productSpec.sku })
        if ($products.Count -eq 0) {
            $product = Invoke-MercatoApi -Path "/products" -Method "POST" -Body @{
                vendor_id = [int] $vendor.vendor_id
                title = $productSpec.title
                description = $productSpec.description
                sku = $productSpec.sku
                price_minor = [int] $productSpec.price_minor
                stock_quantity = [int] $productSpec.stock_quantity
                status = "active"
            }
            $created += $product
        }
    }
}

[pscustomobject]@{
    status = "seeded"
    vendors = $vendors.Count
    new_products = $created.Count
    base_url = $baseUrl
    storefront = "$baseUrl/"
    admin = "$baseUrl/wp-admin/admin.php?page=mercato-admin"
    vendor_console = "$baseUrl/wp-admin/admin.php?page=mercato-vendor"
} | ConvertTo-Json -Depth 5
