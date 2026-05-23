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
        [object]$Body = $null,
        [string]$TenantSlug = ""
    )

    $headers = @{ "X-Mercato-Test-Secret" = $secret }
    $prefix = ""
    if ($TenantSlug -ne "") {
        $prefix = "/t/$TenantSlug"
    }
    $uri = "$baseUrl$prefix/?rest_route=/mercato/v1$Path"
    try {
        if ($Body -eq $null) {
            return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers
        }

        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 20)
    } catch {
        throw "Mercato API request failed: $Method $uri :: $($_.Exception.Message)"
    }
}

$storefront = @{
    brand = "Gigsii"
    mark = "G"
    title = "Gigsii Service Marketplace"
    hero_headline = "The service marketplace for homeowners who want the job handled right."
    hero_copy = "Discover verified local providers, compare bookable services, and keep every job moving from request to completion. Gigsii is powered by Mercato as a Xusmo-ready marketplace tenant."
    primary_cta = "Open tenant admin"
    secondary_cta = "Open provider console"
    catalog_headline = "Popular services ready to book"
    catalog_copy = "Curated local service cards with provider routing, category support, and booking capacity."
    catalog_badge = "Verified local help"
    vendor_headline = "Providers customers can trust"
    vendor_copy = "Approved service companies with clear service areas, operational workflows, and tenant-scoped accountability."
    vendor_badge = "Verified network"
    buyer_headline = "A cleaner path from request to done"
    buyer_copy = "Clients choose the service and the provider offering, then Mercato keeps fulfillment, tracking, notifications, and audit evidence aligned."
    seller_headline = "Built for serious service operators"
    seller_copy = "Providers can publish services, manage assigned work, respond to clients, and grow inside a marketplace that feels professional from day one."
    workflow_headline = "How Gigsii works inside Xusmo"
    workflow_copy = "Xusmo creates the site, Mercato powers the marketplace, and every tenant gets isolated providers, services, configuration, and operations."
    footer = "Gigsii tenant running inside the Mercato SaaS platform"
    item_empty_title = "No Gigsii services yet"
    item_empty_copy = "Run tools\seed-gigsii-tenant.ps1 to create providers and services."
    item_fallback_copy = "Verified local service ready for booking."
    item_quantity_label = "booking slots"
    vendor_status_label = "verified provider"
    nav = @(
        @{ href = "#categories"; label = "Categories" },
        @{ href = "#shop"; label = "Services" },
        @{ href = "#vendors"; label = "Providers" },
        @{ href = "#buyer"; label = "Client" },
        @{ href = "#requests"; label = "Requests" },
        @{ href = "#features"; label = "Features" },
        @{ href = "#operations"; label = "Operations" },
        @{ href = "#seller"; label = "Provider" },
        @{ href = "/wp-admin/admin.php?page=mercato-admin"; label = "Admin" }
    )
    metric_labels = @{
        vendors = "Approved providers"
        products = "Live services"
        orders = "Jobs tracked"
        take = "Marketplace fees"
    }
    positioning_cards = @(
        @{ eyebrow = "Trust"; title = "Verified from the start"; copy = "Providers are approved before they appear in the marketplace." },
        @{ eyebrow = "Choice"; title = "One service, many providers"; copy = "Customers can compare local providers offering the same service." },
        @{ eyebrow = "Local"; title = "Service areas built in"; copy = "Categories, service radius, and provider locations are scoped per tenant." },
        @{ eyebrow = "Ops"; title = "From booking to completion"; copy = "Orders, jobs, messages, notifications, and audit stay connected." }
    )
    seller_steps = @(
        @{ eyebrow = "Join"; title = "A polished provider profile"; copy = "Service businesses can present their work with trust and clarity." },
        @{ eyebrow = "Offer"; title = "Bookable service catalog"; copy = "Each provider can attach pricing, duration, capacity, and service area." },
        @{ eyebrow = "Route"; title = "Correct provider fulfillment"; copy = "Checkout preserves the selected offering so the work routes correctly." },
        @{ eyebrow = "Update"; title = "__NOTIFICATION_SUMMARY__"; copy = "Email and event updates keep clients and operators aligned." },
        @{ eyebrow = "Control"; title = "Tenant operations evidence"; copy = "Audit, outbox, reports, and feature flags stay isolated." },
        @{ eyebrow = "Grow"; title = "Ready for Xusmo sites"; copy = "Xusmo can enable this marketplace experience per website." }
    )
    workflow_steps = @(
        @{ eyebrow = "01"; title = "Xusmo enables Mercato"; copy = "The Xusmo site maps to a Mercato tenant ID." },
        @{ eyebrow = "02"; title = "Tenant config applies"; copy = "Storefront, domains, integrations, and flags are tenant settings." },
        @{ eyebrow = "03"; title = "Providers publish offers"; copy = "Many providers can offer shared service templates." },
        @{ eyebrow = "04"; title = "Clients book offerings"; copy = "Order splitting uses selected offering metadata for correct provider routing." }
    )
}

$tenant = Invoke-MercatoApi -Path "/enterprise/tenants" -Method "POST" -Body @{
    tenant_slug = "gigsii"
    display_name = "Gigsii"
    plan_code = "xusmo-marketplace"
    region_code = "ca-central-1"
    control_plane_id = "xusmo-site-gigsii-local"
    domains = @(
        @{ domain = "localhost"; path_prefix = "/t/gigsii"; is_primary = $true; status = "active"; verified = $true }
    )
    feature_flags = @{
        "mercato.ai" = $true
        "mercato.collaboration" = $true
        "mercato.commissions" = $true
        "mercato.core" = $true
        "mercato.disputes" = $true
        "mercato.enterprise" = $true
        "mercato.fraud" = $true
        "mercato.integration.avalara" = $true
        "mercato.integration.aws_s3" = $true
        "mercato.integration.paypal" = $true
        "mercato.integration.postmark" = $true
        "mercato.integration.sendgrid" = $true
        "mercato.integration.shippo" = $true
        "mercato.integration.stripe" = $true
        "mercato.integration.stripe_connect" = $true
        "mercato.integration.taxjar" = $true
        "mercato.integration.twilio" = $true
        "mercato.kyc" = $true
        "mercato.messaging" = $true
        "mercato.migration" = $true
        "mercato.notifications" = $true
        "mercato.orders" = $true
        "mercato.payouts" = $true
        "mercato.products" = $true
        "mercato.promotions" = $true
        "mercato.reports" = $true
        "mercato.reviews" = $true
        "mercato.search" = $true
        "mercato.service_ops" = $true
        "mercato.subscriptions" = $true
        "mercato.tax" = $true
        "mercato.vendors" = $true
        "gigsii.otp" = $true
        "gigsii.monetization" = $true
        "gigsii.task_posting" = $true
        "gigsii.referral_redemption" = $true
    }
    integrations = @{
        sendgrid = @{
            status = "test"
            public_config = @{ from_email = "no-reply@gigsii.local"; mode = "mailpit" }
            secret_refs = @{ api_key = "env:SENDGRID_API_KEY" }
        }
        s3 = @{
            status = "test"
            public_config = @{ bucket = "mercato-local"; endpoint = "http://localhost:9002" }
            secret_refs = @{ access_key = "env:MERCATO_S3_ACCESS_KEY"; secret_key = "env:MERCATO_S3_SECRET_KEY" }
        }
        stripe = @{
            status = "test"
            public_config = @{ mode = "test"; connect = $true; payouts = "sandbox" }
            secret_refs = @{ secret_key = "env:STRIPE_SECRET_KEY"; webhook_secret = "env:STRIPE_WEBHOOK_SECRET" }
        }
    }
    storefront = $storefront
}

$categories = @("Plumbing", "Cleaning", "Appliance Repair", "Electrical", "Handyman")
$categoryByName = @{}
foreach ($categoryName in $categories) {
    $slug = ($categoryName.ToLowerInvariant() -replace '[^a-z0-9]+', '-').Trim('-')
    $existing = @(Invoke-MercatoApi -TenantSlug "gigsii" -Path "/categories" | ForEach-Object { $_ } | Where-Object { $_.slug -eq $slug })
    if ($existing.Count -gt 0) {
        $categoryByName[$categoryName] = $existing[0]
    } else {
        $categoryByName[$categoryName] = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/categories" -Method "POST" -Body @{ name = $categoryName; slug = $slug }
    }
}

$providers = @(
    @{ business_name = "MapleFix Home Services"; store_slug = "maplefix"; category = "Plumbing"; latitude = 43.6532; longitude = -79.3832; service_radius_km = 30; services = @(
        @{ title = "Emergency Leak Diagnosis"; sku = "GIGSII-LEAK"; price_minor = 12500; stock_quantity = 12; description = "Licensed plumbing assessment with arrival window, photos, and repair estimate." },
        @{ title = "Faucet Replacement Visit"; sku = "GIGSII-FAUCET"; price_minor = 18500; stock_quantity = 8; description = "On-site faucet replacement with parts review and completion notes." }
    ) },
    @{ business_name = "BrightNest Cleaning Co"; store_slug = "brightnest"; category = "Cleaning"; latitude = 43.6605; longitude = -79.4320; service_radius_km = 25; services = @(
        @{ title = "Move-Out Deep Clean"; sku = "GIGSII-MOVEOUT"; price_minor = 32000; stock_quantity = 10; description = "Turnover clean with checklist, supplies, and photo proof." },
        @{ title = "Recurring Home Cleaning"; sku = "GIGSII-RECURRING"; price_minor = 14500; stock_quantity = 20; description = "Standard recurring home visit for kitchens, baths, floors, and reset tasks." }
    ) },
    @{ business_name = "UrbanSpark Electric"; store_slug = "urbanspark"; category = "Electrical"; latitude = 43.6440; longitude = -79.4000; service_radius_km = 35; services = @(
        @{ title = "Smart Lighting Install"; sku = "GIGSII-LIGHTING"; price_minor = 17500; stock_quantity = 11; description = "Install connected dimmers, fixtures, and room scenes with safety verification." },
        @{ title = "Panel Safety Inspection"; sku = "GIGSII-PANEL"; price_minor = 15500; stock_quantity = 7; description = "Breaker panel inspection with findings, photos, and follow-up estimate." }
    ) }
)

$createdServices = 0
foreach ($providerSpec in $providers) {
    $existingVendors = @(Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors" | ForEach-Object { $_ } | Where-Object { $_.store_slug -eq $providerSpec.store_slug })
    if ($existingVendors.Count -gt 0) {
        $provider = $existingVendors[0]
    } else {
        $provider = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors" -Method "POST" -Body @{
            business_name = $providerSpec.business_name
            store_slug = $providerSpec.store_slug
            return_policy = "Service appointments can be rescheduled before dispatch."
        }
    }

    Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/status" -Method "POST" -Body @{ status = "approved" } | Out-Null
    Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/locations" -Method "POST" -Body @{
        label = "$($providerSpec.business_name) service area"
        city = "Toronto"
        region = "ON"
        country = "CA"
        latitude = [double] $providerSpec.latitude
        longitude = [double] $providerSpec.longitude
        service_radius_km = [double] $providerSpec.service_radius_km
        is_primary = $true
    } | Out-Null

    foreach ($serviceSpec in $providerSpec.services) {
        $existingServices = @(Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products" | ForEach-Object { $_ } | Where-Object { $_.sku -eq $serviceSpec.sku })
        if ($existingServices.Count -eq 0) {
            Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products" -Method "POST" -Body @{
                vendor_id = [int] $provider.vendor_id
                title = $serviceSpec.title
                description = $serviceSpec.description
                sku = $serviceSpec.sku
                price_minor = [int] $serviceSpec.price_minor
                stock_quantity = [int] $serviceSpec.stock_quantity
                category_ids = @([int] $categoryByName[$providerSpec.category].category_id)
                duration_minutes = 90
                lead_time_minutes = 180
                capacity = 1
                status = "active"
            } | Out-Null
            $createdServices++
        }
    }
}

$services = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products"
$vendors = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors"
$serviceCount = @($services | ForEach-Object { $_ }).Count
$vendorCount = @($vendors | ForEach-Object { $_ }).Count

[pscustomobject]@{
    status = "seeded"
    tenant_id = $tenant.tenant_id
    tenant_slug = $tenant.tenant_slug
    storefront = "$baseUrl/t/gigsii"
    vendors = $vendorCount
    services = $serviceCount
    new_services = $createdServices
} | ConvertTo-Json -Depth 8
