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
        if ($null -eq $Body) {
            return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers
        }
        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 20)
    } catch {
        throw "Mercato API request failed: $Method $uri :: $($_.Exception.Message)"
    }
}

# --- Storefront config (preserved from previous run, light edits only) ---
$storefront = @{
    theme = "taskfirst"
    brand = "Gigsii"
    mark = "g"
    title = "Gigsii - What needs doing today?"
    hero_headline = "What needs doing today?"
    hero_copy = "Tell us what's broken - or what you'd love off your list. Local pros respond with a quote in minutes."
    primary_cta = "Find me help"
    secondary_cta = "List your trade"
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
    footer = "Gigsii tenant running inside the Mercato SaaS platform"
    item_empty_title = "No Gigsii services yet"
    item_empty_copy = "Run tools\seed-gigsii-tenant.ps1 to create providers and services."
    item_fallback_copy = "Verified local service ready for booking."
    item_quantity_label = "booking slots"
    vendor_status_label = "verified provider"
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
}

$tenant = Invoke-MercatoApi -Path "/enterprise/tenants" -Method "POST" -Body @{
    tenant_slug = "gigsii"
    display_name = "Gigsii"
    plan_code = "xusmo-marketplace"
    storefront = $storefront
}

# --- Categories ---
$categories = @(
    "Plumbing",
    "Cleaning",
    "Electrical",
    "HVAC",
    "Handyman",
    "Landscaping",
    "Painting",
    "Moving",
    "Pest Control",
    "Locksmith",
    "Roofing",
    "Web & Marketing"
)
$categoryByName = @{}
$existingCategories = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/categories"
foreach ($existing in $existingCategories) {
    $categoryByName[$existing.name] = $existing
}
foreach ($categoryName in $categories) {
    if (-not $categoryByName.ContainsKey($categoryName)) {
        $slug = ($categoryName.ToLower() -replace '[^a-z0-9]+', '-').Trim('-')
        $categoryByName[$categoryName] = Invoke-MercatoApi -TenantSlug "gigsii" -Path "/categories" -Method "POST" -Body @{ name = $categoryName; slug = $slug }
    }
}

# --- Providers (with photos and zones) ---
$providers = @(
    @{ business_name="MapleFix Home Services"; store_slug="maplefix"; category="Plumbing";
       headline="Licensed plumber, 18 years on Toronto kitchens.";
       bio="Family-run plumbing serving the GTA since 2007. Same-day for emergencies, transparent flat-rate quotes, and parts warranty on every job.";
       years_experience=18; hourly_rate_minor=11500; phone="+1-416-555-0142";
       languages="English, Mandarin"; license_number="ON-P-44219";
       insurance_carrier="Intact"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=1200&h=400&fit=crop&auto=format";
       latitude=43.6532; longitude=-79.3832; service_radius_km=30;
       extra_areas=@(
            @{label="Downtown Toronto"; city="Toronto"; region="ON"; latitude=43.6532; longitude=-79.3832; radius_km=15},
            @{label="Etobicoke"; city="Etobicoke"; region="ON"; latitude=43.6205; longitude=-79.5132; radius_km=20},
            @{label="North York"; city="Toronto"; region="ON"; latitude=43.7615; longitude=-79.4111; radius_km=12}
       );
       services=@(
            @{ title="Emergency Leak Diagnosis"; sku="GIGSII-LEAK"; price_minor=12500; stock_quantity=12; description="Licensed plumbing assessment with arrival window, photos, and repair estimate."; pricing_type="fixed"; duration_minutes=60 },
            @{ title="Faucet Replacement Visit"; sku="GIGSII-FAUCET"; price_minor=18500; stock_quantity=8; description="On-site faucet replacement with parts review and completion notes."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="Hourly Plumbing Labour"; sku="GIGSII-PLUMBHR"; price_minor=11500; stock_quantity=99; description="By-the-hour for non-standard jobs. Parts at cost."; pricing_type="hourly"; duration_minutes=60 },
            @{ title="Water Heater Install"; sku="GIGSII-WATERHEATER"; price_minor=95000; stock_quantity=4; description="Tank or tankless installation, permit handling, old-unit removal."; pricing_type="quote_required"; duration_minutes=240 }
       ) },
    @{ business_name="BrightNest Cleaning Co"; store_slug="brightnest"; category="Cleaning";
       headline="Eco-friendly cleaning, recurring or one-off.";
       bio="Crew of five, all WSIB-covered, all background-checked. Non-toxic supplies provided. Bond-back guarantee on move-outs.";
       years_experience=9; hourly_rate_minor=7500; phone="+1-416-555-0188";
       languages="English, Portuguese"; license_number="BL-9912";
       insurance_carrier="Co-operators"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=1200&h=400&fit=crop&auto=format";
       latitude=43.6605; longitude=-79.432; service_radius_km=25;
       extra_areas=@(
            @{label="Annex / Yorkville"; city="Toronto"; region="ON"; latitude=43.67; longitude=-79.405; radius_km=8},
            @{label="Liberty Village"; city="Toronto"; region="ON"; latitude=43.6385; longitude=-79.4225; radius_km=8},
            @{label="Leslieville"; city="Toronto"; region="ON"; latitude=43.6646; longitude=-79.3392; radius_km=6}
       );
       services=@(
            @{ title="Move-Out Deep Clean"; sku="GIGSII-MOVEOUT"; price_minor=32000; stock_quantity=10; description="Turnover clean with checklist, supplies, and photo proof."; pricing_type="fixed"; duration_minutes=240 },
            @{ title="Recurring Home Cleaning"; sku="GIGSII-RECURRING"; price_minor=14500; stock_quantity=20; description="Standard recurring home visit for kitchens, baths, floors, and reset tasks."; pricing_type="fixed"; duration_minutes=150 },
            @{ title="Window Cleaning"; sku="GIGSII-WIN"; price_minor=800; stock_quantity=99; description="Inside + outside, frame wipe-down, screens rinsed."; pricing_type="per_unit"; unit_label="window"; duration_minutes=15 },
            @{ title="Post-Renovation Cleanup"; sku="GIGSII-POSTRENO"; price_minor=48000; stock_quantity=6; description="Dust + debris + paint-fleck removal after construction."; pricing_type="fixed"; duration_minutes=300 }
       ) },
    @{ business_name="UrbanSpark Electric"; store_slug="urbanspark"; category="Electrical";
       headline="ESA-licensed master electrician. Smart-home specialist.";
       bio="Master electrician with 22 years on residential and light commercial work. Specialises in smart-home retrofits, EV charger installs, and panel upgrades.";
       years_experience=22; hourly_rate_minor=13500; phone="+1-416-555-0203";
       languages="English"; license_number="ESA-77310";
       insurance_carrier="Aviva"; insurance_amount_minor=500000000;
       photo_url="https://images.unsplash.com/photo-1565608087341-404b25492fee?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1565608087341-404b25492fee?w=1200&h=400&fit=crop&auto=format";
       latitude=43.644; longitude=-79.4; service_radius_km=35;
       extra_areas=@(
            @{label="Midtown Toronto"; city="Toronto"; region="ON"; latitude=43.7065; longitude=-79.3984; radius_km=12},
            @{label="Scarborough"; city="Scarborough"; region="ON"; latitude=43.7764; longitude=-79.2318; radius_km=18}
       );
       services=@(
            @{ title="Smart Lighting Install"; sku="GIGSII-LIGHTING"; price_minor=17500; stock_quantity=11; description="Install connected dimmers, fixtures, and room scenes with safety verification."; pricing_type="fixed"; duration_minutes=120 },
            @{ title="Panel Safety Inspection"; sku="GIGSII-PANEL"; price_minor=15500; stock_quantity=7; description="Breaker panel inspection with findings, photos, and follow-up estimate."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="EV Charger Install (Level 2)"; sku="GIGSII-EV"; price_minor=85000; stock_quantity=5; description="Hardwired Level-2 EV charger install including permit and inspection."; pricing_type="quote_required"; duration_minutes=240 }
       ) },
    @{ business_name="TrueNorth HVAC"; store_slug="truenorth-hvac"; category="HVAC";
       headline="Heating, cooling, ventilation. TSSA-certified.";
       bio="TSSA G2-certified HVAC techs. 24/7 emergency furnace service across the GTA. Lennox, Carrier, Daikin authorised dealer.";
       years_experience=15; hourly_rate_minor=14500; phone="+1-647-555-0234";
       languages="English, French"; license_number="TSSA-G2-88122";
       insurance_carrier="Intact"; insurance_amount_minor=500000000;
       photo_url="https://images.unsplash.com/photo-1503389152951-9f343605f61e?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1503389152951-9f343605f61e?w=1200&h=400&fit=crop&auto=format";
       latitude=43.7615; longitude=-79.4111; service_radius_km=40;
       extra_areas=@(
            @{label="North York"; city="Toronto"; region="ON"; latitude=43.7615; longitude=-79.4111; radius_km=15},
            @{label="Vaughan"; city="Vaughan"; region="ON"; latitude=43.8361; longitude=-79.4983; radius_km=20},
            @{label="Markham"; city="Markham"; region="ON"; latitude=43.8561; longitude=-79.337; radius_km=20}
       );
       services=@(
            @{ title="Furnace Tune-Up"; sku="GIGSII-FURNACE-TUNE"; price_minor=18900; stock_quantity=30; description="30-point furnace inspection and clean. Includes filter swap."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="AC Diagnostic Visit"; sku="GIGSII-AC-DIAG"; price_minor=14900; stock_quantity=20; description="Cooling fault diagnosis with repair quote within the same visit."; pricing_type="fixed"; duration_minutes=60 },
            @{ title="Heat Pump Consultation"; sku="GIGSII-HEATPUMP"; price_minor=0; stock_quantity=99; description="Free in-home assessment for cold-climate heat-pump retrofits."; pricing_type="quote_required"; duration_minutes=60 }
       ) },
    @{ business_name="Kingsway Handyman"; store_slug="kingsway-handyman"; category="Handyman";
       headline="Drywall, mounting, assembly. One-day jobs my specialty.";
       bio="Solo handyman covering the small jobs the big firms skip. TV mounts, IKEA assembly, drywall patches, picture rails, shelving.";
       years_experience=7; hourly_rate_minor=8500; phone="+1-647-555-0299";
       languages="English"; license_number="";
       insurance_carrier="Wawanesa"; insurance_amount_minor=100000000;
       photo_url="https://images.unsplash.com/photo-1581094288338-2314dddb7ece?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1581094288338-2314dddb7ece?w=1200&h=400&fit=crop&auto=format";
       latitude=43.6519; longitude=-79.5074; service_radius_km=25;
       extra_areas=@(
            @{label="Etobicoke"; city="Etobicoke"; region="ON"; latitude=43.6205; longitude=-79.5132; radius_km=15},
            @{label="High Park"; city="Toronto"; region="ON"; latitude=43.6465; longitude=-79.4637; radius_km=10},
            @{label="The Junction"; city="Toronto"; region="ON"; latitude=43.666; longitude=-79.469; radius_km=8}
       );
       services=@(
            @{ title="TV Mount Install (up to 65 in)"; sku="GIGSII-TVMOUT"; price_minor=12500; stock_quantity=30; description="Wall mount with cable management. Bring your own mount or add one."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="IKEA Assembly"; sku="GIGSII-IKEA"; price_minor=7500; stock_quantity=50; description="Per item assembly. Wardrobes and kitchens by quote."; pricing_type="per_unit"; unit_label="item"; duration_minutes=45 },
            @{ title="Drywall Patch + Paint"; sku="GIGSII-DRYWALL"; price_minor=18500; stock_quantity=20; description="Patch up to 4 holes per visit, sanded smooth, matched paint."; pricing_type="fixed"; duration_minutes=150 },
            @{ title="Handyman Hour"; sku="GIGSII-HANDY-HR"; price_minor=8500; stock_quantity=99; description="By-the-hour for mixed small jobs."; pricing_type="hourly"; duration_minutes=60 }
       ) },
    @{ business_name="Verde Landscape"; store_slug="verde-landscape"; category="Landscaping";
       headline="Lawn, garden, hardscape. Spring-to-fall maintenance plans.";
       bio="Family-owned since 2011. Sustainable native-plant design, seasonal lawn care, paver patios, retaining walls.";
       years_experience=13; hourly_rate_minor=7200; phone="+1-905-555-0314";
       languages="English, Italian"; license_number="LO-22887";
       insurance_carrier="Northbridge"; insurance_amount_minor=300000000;
       photo_url="https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1200&h=400&fit=crop&auto=format";
       latitude=43.589; longitude=-79.6441; service_radius_km=35;
       extra_areas=@(
            @{label="Mississauga"; city="Mississauga"; region="ON"; latitude=43.589; longitude=-79.6441; radius_km=25},
            @{label="Oakville"; city="Oakville"; region="ON"; latitude=43.4675; longitude=-79.6877; radius_km=15},
            @{label="Burlington"; city="Burlington"; region="ON"; latitude=43.3255; longitude=-79.799; radius_km=15}
       );
       services=@(
            @{ title="Weekly Lawn Service"; sku="GIGSII-LAWN-WK"; price_minor=5500; stock_quantity=40; description="Mow, edge, blow. Min 4-visit booking. Includes cleanup."; pricing_type="fixed"; duration_minutes=30 },
            @{ title="Spring Garden Bed Refresh"; sku="GIGSII-GARDEN"; price_minor=28000; stock_quantity=12; description="Cleanup, edging, mulching, perennial split. Half-day visit."; pricing_type="fixed"; duration_minutes=240 },
            @{ title="Hardscape Consultation"; sku="GIGSII-HARDSCAPE"; price_minor=0; stock_quantity=99; description="Patio / walkway / retaining-wall design with estimate."; pricing_type="quote_required"; duration_minutes=60 }
       ) },
    @{ business_name="Brushwork Painters"; store_slug="brushwork-painters"; category="Painting";
       headline="Interior + exterior painting. Drips-free guarantee.";
       bio="Crew of three with 10+ years of residential painting. Sherwin Williams, Benjamin Moore preferred. Drop-cloths and tape every time.";
       years_experience=11; hourly_rate_minor=7800; phone="+1-416-555-0421";
       languages="English, Spanish"; license_number="";
       insurance_carrier="Aviva"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1562259949-e8e7689d7828?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1562259949-e8e7689d7828?w=1200&h=400&fit=crop&auto=format";
       latitude=43.666; longitude=-79.469; service_radius_km=30;
       extra_areas=@(
            @{label="The Junction"; city="Toronto"; region="ON"; latitude=43.666; longitude=-79.469; radius_km=10},
            @{label="Roncesvalles"; city="Toronto"; region="ON"; latitude=43.6481; longitude=-79.4486; radius_km=8},
            @{label="Bloor West Village"; city="Toronto"; region="ON"; latitude=43.6489; longitude=-79.4756; radius_km=6}
       );
       services=@(
            @{ title="Single-Room Repaint"; sku="GIGSII-PAINT-ROOM"; price_minor=42000; stock_quantity=25; description="Walls only, two coats, primer where needed. Paint included up to `$80."; pricing_type="fixed"; duration_minutes=360 },
            @{ title="Whole-Home Interior"; sku="GIGSII-PAINT-HOUSE"; price_minor=0; stock_quantity=99; description="Multi-day project for 3+ bed homes. Quote after walk-through."; pricing_type="quote_required"; duration_minutes=480 },
            @{ title="Exterior Trim"; sku="GIGSII-PAINT-TRIM"; price_minor=7200; stock_quantity=99; description="Per linear-foot for door / window / fascia trim."; pricing_type="per_unit"; unit_label="linear foot"; duration_minutes=20 }
       ) },
    @{ business_name="Lift & Shift Movers"; store_slug="lift-and-shift"; category="Moving";
       headline="Local moves done right. Two movers, one truck, flat rate.";
       bio="Toronto-based movers since 2015. WSIB-covered, hourly billing, no fuel surcharge, packing materials provided.";
       years_experience=9; hourly_rate_minor=16000; phone="+1-647-555-0511";
       languages="English, Russian"; license_number="MTO-77441";
       insurance_carrier="Northbridge"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=1200&h=400&fit=crop&auto=format";
       latitude=43.7065; longitude=-79.3984; service_radius_km=50;
       extra_areas=@(
            @{label="Midtown Toronto"; city="Toronto"; region="ON"; latitude=43.7065; longitude=-79.3984; radius_km=15},
            @{label="Scarborough"; city="Scarborough"; region="ON"; latitude=43.7764; longitude=-79.2318; radius_km=18},
            @{label="Mississauga"; city="Mississauga"; region="ON"; latitude=43.589; longitude=-79.6441; radius_km=25}
       );
       services=@(
            @{ title="Local Move (2 movers + truck, hourly)"; sku="GIGSII-MOVE-HR"; price_minor=16000; stock_quantity=99; description="Two movers + 26-ft truck. 3-hour minimum."; pricing_type="hourly"; duration_minutes=180 },
            @{ title="Studio / 1-Bed Flat Move"; sku="GIGSII-MOVE-1BR"; price_minor=65000; stock_quantity=15; description="Up to 4 hours, dolly + blankets included."; pricing_type="fixed"; duration_minutes=240 },
            @{ title="Piano Move"; sku="GIGSII-MOVE-PIANO"; price_minor=35000; stock_quantity=8; description="Upright or grand. Includes specialist gear."; pricing_type="fixed"; duration_minutes=180 }
       ) },
    @{ business_name="Sentinel Pest Control"; store_slug="sentinel-pest"; category="Pest Control";
       headline="Licensed exterminator. Cockroaches, mice, bedbugs, wasps.";
       bio="Health Canada-licensed pest tech with 14 years across GTA condos and houses. Discreet unmarked vehicles, pet-safe treatments.";
       years_experience=14; hourly_rate_minor=12500; phone="+1-416-555-0633";
       languages="English, Cantonese"; license_number="HC-PCO-44872";
       insurance_carrier="Co-operators"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1626621341517-bbf3d9990a23?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1626621341517-bbf3d9990a23?w=1200&h=400&fit=crop&auto=format";
       latitude=43.6646; longitude=-79.3392; service_radius_km=35;
       extra_areas=@(
            @{label="Leslieville"; city="Toronto"; region="ON"; latitude=43.6646; longitude=-79.3392; radius_km=8},
            @{label="The Beaches"; city="Toronto"; region="ON"; latitude=43.6716; longitude=-79.2941; radius_km=8},
            @{label="Scarborough"; city="Scarborough"; region="ON"; latitude=43.7764; longitude=-79.2318; radius_km=18}
       );
       services=@(
            @{ title="Bedbug Treatment (1-bed)"; sku="GIGSII-BEDBUG"; price_minor=48000; stock_quantity=10; description="Two-visit thermal/chemical treatment with 30-day guarantee."; pricing_type="fixed"; duration_minutes=240 },
            @{ title="Mouse / Rodent Setup"; sku="GIGSII-MICE"; price_minor=22000; stock_quantity=20; description="Initial inspection, bait + trap placement, return visit."; pricing_type="fixed"; duration_minutes=120 },
            @{ title="Wasp Nest Removal"; sku="GIGSII-WASP"; price_minor=18500; stock_quantity=30; description="Same-day in-season. Treatment + nest removal."; pricing_type="fixed"; duration_minutes=60 }
       ) },
    @{ business_name="GoldKey Locksmiths"; store_slug="goldkey"; category="Locksmith";
       headline="24/7 emergency lockout, rekeys, smart locks.";
       bio="Mobile locksmiths covering Toronto and Peel. 30-minute response in core neighborhoods. Insured, bonded, background-checked techs.";
       years_experience=12; hourly_rate_minor=11000; phone="+1-416-555-0755";
       languages="English"; license_number="ON-LSM-22198";
       insurance_carrier="Intact"; insurance_amount_minor=200000000;
       photo_url="https://images.unsplash.com/photo-1582139329536-e7284fece509?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1582139329536-e7284fece509?w=1200&h=400&fit=crop&auto=format";
       latitude=43.6532; longitude=-79.3832; service_radius_km=40;
       extra_areas=@(
            @{label="Downtown Toronto"; city="Toronto"; region="ON"; latitude=43.6532; longitude=-79.3832; radius_km=15},
            @{label="Mississauga"; city="Mississauga"; region="ON"; latitude=43.589; longitude=-79.6441; radius_km=25},
            @{label="Brampton"; city="Brampton"; region="ON"; latitude=43.7315; longitude=-79.7624; radius_km=20}
       );
       services=@(
            @{ title="Emergency Lockout"; sku="GIGSII-LOCKOUT"; price_minor=14500; stock_quantity=99; description="24/7 dispatch within service area. Flat call-out + work."; pricing_type="fixed"; duration_minutes=60 },
            @{ title="Rekey up to 4 cylinders"; sku="GIGSII-REKEY"; price_minor=18900; stock_quantity=30; description="Mobile rekey of existing locks. Includes 4 keys."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="Smart Lock Install"; sku="GIGSII-SMARTLOCK"; price_minor=22500; stock_quantity=15; description="Install of August / Schlage / Yale. Wi-Fi setup included."; pricing_type="fixed"; duration_minutes=90 }
       ) },
    @{ business_name="Apex Roofing Co"; store_slug="apex-roofing"; category="Roofing";
       headline="Shingle, flat, repair. 25-year workmanship guarantee.";
       bio="Roofing specialists since 2003. IKO, GAF, and BP authorised. WSIB-compliant crews. Detailed quote within 48 hours.";
       years_experience=21; hourly_rate_minor=0; phone="+1-647-555-0877";
       languages="English"; license_number="ORCA-44119";
       insurance_carrier="Aviva"; insurance_amount_minor=500000000;
       photo_url="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=1200&h=400&fit=crop&auto=format";
       latitude=43.7764; longitude=-79.2318; service_radius_km=45;
       extra_areas=@(
            @{label="Scarborough"; city="Scarborough"; region="ON"; latitude=43.7764; longitude=-79.2318; radius_km=20},
            @{label="Markham"; city="Markham"; region="ON"; latitude=43.8561; longitude=-79.337; radius_km=18},
            @{label="Pickering"; city="Pickering"; region="ON"; latitude=43.8384; longitude=-79.0868; radius_km=15}
       );
       services=@(
            @{ title="Roof Inspection Report"; sku="GIGSII-ROOF-INSPECT"; price_minor=19500; stock_quantity=20; description="Full-roof inspection with photo report and quote."; pricing_type="fixed"; duration_minutes=90 },
            @{ title="Shingle Repair (small)"; sku="GIGSII-SHINGLE-FIX"; price_minor=35000; stock_quantity=12; description="Up to 10 shingles replaced and sealed."; pricing_type="fixed"; duration_minutes=150 },
            @{ title="Full Re-Roof"; sku="GIGSII-REROOF"; price_minor=0; stock_quantity=99; description="Tear-off + reinstall on residential roof. Quoted after inspection."; pricing_type="quote_required"; duration_minutes=480 }
       ) },
    @{ business_name="PixelPress Web Studio"; store_slug="pixelpress"; category="Web & Marketing";
       headline="Websites for tradespeople. Done in two weeks.";
       bio="Toronto micro-agency that builds and runs websites for local service businesses. WordPress, Webflow, plus Google Business Profile + review automation.";
       years_experience=8; hourly_rate_minor=12500; phone="+1-647-555-0922";
       languages="English, French"; license_number="";
       insurance_carrier="Wawanesa"; insurance_amount_minor=100000000;
       photo_url="https://images.unsplash.com/photo-1559028012-481c04fa702d?w=400&h=400&fit=crop&auto=format"; cover_url="https://images.unsplash.com/photo-1559028012-481c04fa702d?w=1200&h=400&fit=crop&auto=format";
       latitude=43.647; longitude=-79.418; service_radius_km=100;
       extra_areas=@(
            @{label="Queen West"; city="Toronto"; region="ON"; latitude=43.647; longitude=-79.418; radius_km=10},
            @{label="Liberty Village"; city="Toronto"; region="ON"; latitude=43.6385; longitude=-79.4225; radius_km=8}
       );
       services=@(
            @{ title="Starter Website (5 pages)"; sku="GIGSII-WEB-5"; price_minor=195000; stock_quantity=8; description="Brand kit + 5-page site + GBP setup. Two-week turnaround."; pricing_type="fixed"; duration_minutes=480 },
            @{ title="Monthly Care Plan"; sku="GIGSII-WEB-CARE"; price_minor=25000; stock_quantity=30; description="Hosting, updates, monthly content edit, monthly call."; pricing_type="fixed"; duration_minutes=60 },
            @{ title="Hourly Consult"; sku="GIGSII-WEB-HR"; price_minor=12500; stock_quantity=99; description="Marketing strategy / SEO / Google Ads consult by the hour."; pricing_type="hourly"; duration_minutes=60 }
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
            photo_url = $providerSpec.photo_url
            cover_url = $providerSpec.cover_url
        }
    }

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
        photo_url = $providerSpec.photo_url
        cover_url = $providerSpec.cover_url
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
            if ($serviceSpec.ContainsKey("unit_label")) {
                $serviceBody.unit_label = $serviceSpec.unit_label
            }
            Invoke-MercatoApi -TenantSlug "gigsii" -Path "/products" -Method "POST" -Body $serviceBody | Out-Null
            $createdServices++
        }
    }
}

Write-Host ""
Write-Host "Gigsii seed complete:"
Write-Host "  Categories: $($categoryByName.Count)"
Write-Host "  Providers: $($providers.Count)"
Write-Host "  Services created this run: $createdServices"
Write-Host ""
Write-Host "Open these to verify:"
Write-Host "  http://localhost:8092/t/gigsii/signup"
Write-Host "  http://localhost:8092/t/gigsii/providers"
Write-Host "  http://localhost:8092/t/gigsii/services?near=Toronto&radius=25"
Write-Host "  http://localhost:8092/t/gigsii/providers/maplefix"
Write-Host "  http://localhost:8092/wp-admin/admin.php?page=mercato-connectors"
Write-Host "  http://localhost:8092/wp-admin/admin.php?page=mercato-approvals"
