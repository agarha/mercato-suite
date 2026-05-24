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
    # Gigsii uses the Task-First theme overlay (storefront-taskfirst.css
    # + templates/storefront/page-taskfirst.php). This is a per-tenant
    # override; Mercato defaults are unchanged. To revert Gigsii back to
    # the Mercato default look, remove the `theme` key.
    theme = "taskfirst"
    brand = "Gigsii"
    mark = "g"
    title = "Gigsii — What needs doing today?"
    hero_headline = "What needs doing today?"
    hero_copy = "Tell us what's broken — or what you'd love off your list. Local pros respond with a quote in minutes."
    primary_cta = "Find me help"
    secondary_cta = "List your trade"
    # Task-First text overrides. Emoji / decorative glyphs are NOT set here
    # because PowerShell-on-Windows source-file UTF-8 round-tripping for
    # multi-byte chars is unreliable. The PHP template
    # (page-taskfirst.php) ships the emoji defaults inline, so leaving
    # `chips` / `how_steps.icon` / `polaroid_caption` unset here means the
    # PHP defaults win — which is what we want.
    taskfirst = @{
        status_chip = "1,248 pros online . avg. 14 min response"
        hero_lead = "What"
        hero_accent = "needs doing"
        hero_trail = "today?"
        input_label = "Describe it however you want"
        input_text = 'My kitchen sink is leaking under the cabinet...'
        polaroid_label = "Worker in action"
        sticker_top = "smiled"
        sticker_bottom = "at 4:18pm"
        how_eyebrow = "How it goes"
        how_h2_lead = 'From "ugh" to'
        how_h2_em = "fixed"
        how_h2_tail = ", in three messages."
        pros_eyebrow = "People, not algorithms"
        pros_h2_lead = "Meet a few of the"
        pros_h2_em = "1,248"
        pros_h2_tail = "pros nearby."
        pros_link = "Browse the directory"
        provider_cta_eyebrow = "For tradespeople"
        provider_cta_lead = "Your phone, full of"
        provider_cta_em = "real jobs"
        provider_cta_tail = "."
        provider_cta_copy = "Flat 4% on what you earn. No leads. No subscription."
        provider_cta_button = "List your trade"
    }
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
    # Nav uses __TENANT_HOME__ placeholders that Storefront\Config substitutes
    # with /t/<tenant_slug> at render time. These point at real pages
    # introduced in claude/storefront-navigation (Phase 5b) and later branches.
    nav = @(
        @{ href = "__TENANT_HOME__"; label = "Home" },
        @{ href = "__TENANT_HOME__/services"; label = "Services" },
        @{ href = "__TENANT_HOME__/providers"; label = "Providers" },
        @{ href = "__TENANT_HOME__/requests/new"; label = "Post request" },
        @{ href = "__TENANT_HOME__/account"; label = "Client" },
        @{ href = "__TENANT_HOME__/provider/dashboard"; label = "Provider" },
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
    # Flags listed here are tenant-visible on the storefront feature-cloud.
    # Dead/scaffold modules (ai-copilot, collaboration, disputes, fraud-risk,
    # avalara, paypal-marketplace, postmark, shippo, taxjar, twilio, migration,
    # subscriptions-module) are intentionally NOT seeded for the gigsii demo
    # tenant because their backends are 29-LOC scaffolds. Re-enable here once
    # the corresponding modules have real implementations.
    feature_flags = @{
        "mercato.commissions" = $true
        "mercato.core" = $true
        "mercato.enterprise" = $true
        "mercato.integration.aws_s3" = $true
        "mercato.integration.sendgrid" = $true
        "mercato.integration.stripe" = $true
        "mercato.integration.stripe_connect" = $true
        "mercato.kyc" = $true
        "mercato.messaging" = $true
        "mercato.notifications" = $true
        "mercato.orders" = $true
        "mercato.payouts" = $true
        "mercato.products" = $true
        "mercato.promotions" = $true
        "mercato.reports" = $true
        "mercato.reviews" = $true
        "mercato.search" = $true
        "mercato.service_ops" = $true
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
    @{ business_name = "MapleFix Home Services"; store_slug = "maplefix"; category = "Plumbing";
       headline = "Licensed plumber, 18 years on Toronto kitchens.";
       bio = "Family-run plumbing serving the GTA since 2007. Same-day for emergencies, transparent flat-rate quotes, and parts warranty on every job.";
       years_experience = 18; hourly_rate_minor = 11500; phone = "+1-416-555-0142"; languages = "English, Mandarin";
       license_number = "ON-P-44219"; insurance_carrier = "Intact"; insurance_amount_minor = 200000000;
       latitude = 43.6532; longitude = -79.3832; service_radius_km = 30;
       extra_areas = @(@{label="Downtown Toronto"; city="Toronto"; region="ON"; latitude=43.6532; longitude=-79.3832; radius_km=15}, @{label="Etobicoke"; city="Etobicoke"; region="ON"; latitude=43.6205; longitude=-79.5132; radius_km=20});
       services = @(
        @{ title = "Emergency Leak Diagnosis"; sku = "GIGSII-LEAK"; price_minor = 12500; stock_quantity = 12; description = "Licensed plumbing assessment with arrival window, photos, and repair estimate."; pricing_type = "fixed"; duration_minutes = 60 },
        @{ title = "Faucet Replacement Visit"; sku = "GIGSII-FAUCET"; price_minor = 18500; stock_quantity = 8; description = "On-site faucet replacement with parts review and completion notes."; pricing_type = "fixed"; duration_minutes = 90 },
        @{ title = "Hourly Plumbing Labour"; sku = "GIGSII-PLUMBHR"; price_minor = 11500; stock_quantity = 99; description = "By-the-hour for non-standard jobs. Parts at cost."; pricing_type = "hourly"; duration_minutes = 60 }
    ) },
    @{ business_name = "BrightNest Cleaning Co"; store_slug = "brightnest"; category = "Cleaning";
       headline = "Eco-friendly cleaning, recurring or one-off.";
       bio = "Crew of five, all WSIB-covered, all background-checked. Non-toxic supplies provided. Bond-back guarantee on move-outs.";
       years_experience = 9; hourly_rate_minor = 7500; phone = "+1-416-555-0188"; languages = "English, Portuguese";
       license_number = "BL-9912"; insurance_carrier = "Co-operators"; insurance_amount_minor = 200000000;
       latitude = 43.6605; longitude = -79.4320; service_radius_km = 25;
       extra_areas = @(@{label="Annex / Yorkville"; city="Toronto"; region="ON"; latitude=43.6700; longitude=-79.4050; radius_km=8}, @{label="Liberty Village"; city="Toronto"; region="ON"; latitude=43.6385; longitude=-79.4225; radius_km=8});
       services = @(
        @{ title = "Move-Out Deep Clean"; sku = "GIGSII-MOVEOUT"; price_minor = 32000; stock_quantity = 10; description = "Turnover clean with checklist, supplies, and photo proof."; pricing_type = "fixed"; duration_minutes = 240 },
        @{ title = "Recurring Home Cleaning"; sku = "GIGSII-RECURRING"; price_minor = 14500; stock_quantity = 20; description = "Standard recurring home visit for kitchens, baths, floors, and reset tasks."; pricing_type = "fixed"; duration_minutes = 150 },
        @{ title = "Window Cleaning (per window)"; sku = "GIGSII-WIN"; price_minor = 800; stock_quantity = 99; description = "Inside + outside, frame wipe-down, screens rinsed."; pricing_type = "per_unit"; unit_label = "window"; duration_minutes = 15 }
    ) },
    @{ business_name = "UrbanSpark Electric"; store_slug = "urbanspark"; category = "Electrical";
       headline = "ESA-licensed master electrician. Smart-home specialist.";
       bio = "Master electrician with 22 years on residential and light commercial work. Specialises in smart-home retrofits, EV charger installs, and panel upgrades.";
       years_experience = 22; hourly_rate_minor = 13500; phone = "+1-416-555-0203"; languages = "English";
       license_number = "ESA-77310"; insurance_carrier = "Aviva"; insurance_amount_minor = 500000000;
       latitude = 43.6440; longitude = -79.4000; service_radius_km = 35;
       extra_areas = @(@{label="Midtown Toronto"; city="Toronto"; region="ON"; latitude=43.7065; longitude=-79.3984; radius_km=12}, @{label="Scarborough"; city="Scarborough"; region="ON"; latitude=43.7764; longitude=-79.2318; radius_km=18});
       services = @(
        @{ title = "Smart Lighting Install"; sku = "GIGSII-LIGHTING"; price_minor = 17500; stock_quantity = 11; description = "Install connected dimmers, fixtures, and room scenes with safety verification."; pricing_type = "fixed"; duration_minutes = 120 },
        @{ title = "Panel Safety Inspection"; sku = "GIGSII-PANEL"; price_minor = 15500; stock_quantity = 7; description = "Breaker panel inspection with findings, photos, and follow-up estimate."; pricing_type = "fixed"; duration_minutes = 90 },
        @{ title = "EV Charger Install (Level 2)"; sku = "GIGSII-EV"; price_minor = 85000; stock_quantity = 5; description = "Hardwired Level-2 EV charger install including permit and inspection."; pricing_type = "quote_required"; duration_minutes = 240 }
    ) }
)

$createdServices = 0
foreach ($providerSpec in $providers) {
    $existingVendors = @(Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors" | ForEach-Object { $_ } | Where-Object { $_.store_slug -eq $providerSpec.store_slug })
    if ($existingVendors.Count -gt 0) {
        $provider = $existingVendors[0]
    } else {
        # Self-signup style register: send the full profile in one shot so the
        # storefront immediately reflects what a real pro would have filled
        # out via the multi-step form.
        $provider = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors" -Method "POST" -Body @{
            business_name = $providerSpec.business_name
            store_slug = $providerSpec.store_slug
            return_policy = "Service appointments can be rescheduled before dispatch."
            headline = $providerSpec.headline
            bio = $providerSpec.bio
            years_experience = [int] $providerSpec.years_experience
            hourly_rate_minor = [int] $providerSpec.hourly_rate_minor
            currency = "CAD"
            phone = $providerSpec.phone
            languages = $providerSpec.languages
            license_number = $providerSpec.license_number
            insurance_carrier = $providerSpec.insurance_carrier
            insurance_amount_minor = [int] $providerSpec.insurance_amount_minor
        }
    }

    # Ensure profile fields are present even on re-runs against an older
    # vendor row created before the profile migration ran.
    Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/profile" -Method "PATCH" -Body @{
        headline = $providerSpec.headline
        bio = $providerSpec.bio
        years_experience = [int] $providerSpec.years_experience
        hourly_rate_minor = [int] $providerSpec.hourly_rate_minor
        phone = $providerSpec.phone
        languages = $providerSpec.languages
        license_number = $providerSpec.license_number
        insurance_carrier = $providerSpec.insurance_carrier
        insurance_amount_minor = [int] $providerSpec.insurance_amount_minor
    } | Out-Null

    Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/status" -Method "POST" -Body @{ status = "approved" } | Out-Null
    Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/locations" -Method "POST" -Body @{
        label = "$($providerSpec.business_name) HQ"
        city = "Toronto"
        region = "ON"
        country = "CA"
        latitude = [double] $providerSpec.latitude
        longitude = [double] $providerSpec.longitude
        service_radius_km = [double] $providerSpec.service_radius_km
        is_primary = $true
    } | Out-Null

    # Declare extra geo-tagged service areas so the discovery filter has
    # multiple polygons to match against (mirrors TaskRabbit's Work Area Map).
    foreach ($area in $providerSpec.extra_areas) {
        Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$($provider.vendor_id)/service-areas" -Method "POST" -Body @{
            label = $area.label
            city = $area.city
            region = $area.region
            country = "CA"
            latitude = [double] $area.latitude
            longitude = [double] $area.longitude
            radius_km = [double] $area.radius_km
        } | Out-Null
    }

    foreach ($serviceSpec in $providerSpec.services) {
        $existingServices = @(Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products" | ForEach-Object { $_ } | Where-Object { $_.sku -eq $serviceSpec.sku })
        if ($existingServices.Count -eq 0) {
            $serviceBody = @{
                vendor_id = [int] $provider.vendor_id
                title = $serviceSpec.title
                description = $serviceSpec.description
                sku = $serviceSpec.sku
                price_minor = [int] $serviceSpec.price_minor
                stock_quantity = [int] $serviceSpec.stock_quantity
                category_ids = @([int] $categoryByName[$providerSpec.category].category_id)
                duration_minutes = [int] $serviceSpec.duration_minutes
                lead_time_minutes = 180
                capacity = 1
                status = "active"
                pricing_type = $serviceSpec.pricing_type
            }
            if ($serviceSpec.PSObject.Properties.Match('unit_label').Count -gt 0) {
                $serviceBody.unit_label = $serviceSpec.unit_label
            }
            Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products" -Method "POST" -Body $serviceBody | Out-Null
            $createdServices++
        }
    }
}

# Sample reviews per provider. Idempotent: skips when a provider already has
# >= 3 published reviews. Tolerant of the reviews table not existing yet
# (claude/reviews-mvp introduces the migration; if it has not run, the POST
# returns 400 and we just continue).
$reviewsByProvider = @{
    "maplefix" = @(
        @{ rating = 5; title = "Quick fix, clean job"; body = "Booked same-day for a leaking sink trap. Tech arrived in the window, fixed it in 25 minutes, left the area cleaner than they found it." },
        @{ rating = 5; title = "Saved our weekend"; body = "Water heater started leaking Friday night. They came Saturday morning with the right replacement and had us back in hot water by lunch." },
        @{ rating = 4; title = "Solid work, fair price"; body = "Replaced an outdoor hose bib. Quote matched the final invoice. Would book again." }
    )
    "brightnest" = @(
        @{ rating = 5; title = "Best deep clean in years"; body = "Move-out cleaning before we handed over the keys. Got the deposit back in full. Highly recommend." },
        @{ rating = 5; title = "Reliable weekly cleaning"; body = "Have used them every two weeks for four months now. Consistent crew, consistent quality." },
        @{ rating = 4; title = "Good with a quick turnaround"; body = "Needed the place spotless before in-laws arrived. They squeezed us in with a day''s notice." },
        @{ rating = 5; title = "Friendly and thorough"; body = "Crew was professional, paid attention to baseboards and inside cabinets. Worth every penny." }
    )
    "urbanspark" = @(
        @{ rating = 5; title = "Smart-switch install done right"; body = "Replaced six outlets and two light switches with smart-home gear. Labelled the breaker, tested every device, walked us through the app." },
        @{ rating = 4; title = "Diagnosed the flicker"; body = "Living room lights had been flickering for months. Found a loose neutral in the panel, fixed it inside an hour." },
        @{ rating = 5; title = "Professional from quote to invoice"; body = "Clear scope up front, change order documented when we added a circuit. Will use them again for the basement reno." }
    )
}
$reviewedProviders = 0
$reviewsInserted = 0
foreach ($providerSlug in $reviewsByProvider.Keys) {
    try {
        $existingReviews = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors?slug=$providerSlug" -Method "GET"
        # Look up the vendor row for this slug
        $matchingVendor = @($existingReviews | ForEach-Object { $_ } | Where-Object { $_.store_slug -eq $providerSlug })
        if ($matchingVendor.Count -eq 0) {
            Write-Host "  reviews: provider $providerSlug not found, skipping"
            continue
        }
        $vendorId = [int] $matchingVendor[0].vendor_id
        $current = $null
        try {
            $current = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$vendorId/reviews" -Method "GET"
        } catch {
            Write-Host "  reviews: GET /vendors/$vendorId/reviews failed (table probably missing); skipping $providerSlug"
            continue
        }
        $currentCount = if ($current.count) { [int] $current.count } else { 0 }
        if ($currentCount -ge 3) {
            Write-Host "  reviews: $providerSlug already has $currentCount; skipping"
            continue
        }
        foreach ($review in $reviewsByProvider[$providerSlug]) {
            try {
                Invoke-MercatoApi -TenantSlug "gigsii" -Path "/vendors/$vendorId/reviews" -Method "POST" -Body $review | Out-Null
                $reviewsInserted++
            } catch {
                Write-Host "  reviews: insert failed for $providerSlug ($($_.Exception.Message)); continuing"
            }
        }
        $reviewedProviders++
    } catch {
        Write-Host "  reviews: $providerSlug lookup failed ($($_.Exception.Message)); continuing"
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
