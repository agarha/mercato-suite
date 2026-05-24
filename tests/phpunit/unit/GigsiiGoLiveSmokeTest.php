<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pins the Gigsii go-live surface — provider self-signup, geo discovery,
 * multi-service per provider, service areas, and admin-configurable bid
 * limits. These are string-presence smokes: they don't boot WordPress, they
 * just guarantee the wiring exists in the right files. If a future refactor
 * removes any of these, the test fails loudly so we don't ship a regressed
 * Gigsii.
 *
 * Each assertion includes a context message explaining what the missing
 * string means in plain language.
 */
final class GigsiiGoLiveSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testVendorProfileMigrationExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/migrations/0003_vendor_profile.sql';
        $this->assertFileExists($path, 'Vendor profile migration must exist for go-live (headline/bio/license/insurance fields).');
        $sql = file_get_contents($path);
        foreach (['headline', 'bio', 'years_experience', 'hourly_rate_minor', 'license_number', 'insurance_amount_minor', 'background_check_status', 'photo_url'] as $col) {
            $this->assertStringContainsString($col, $sql, "Profile column `$col` must be in migration 0003.");
        }
        $this->assertStringContainsString('mercato_vendor_portfolio', $sql, 'Portfolio table must be in migration 0003.');
    }

    public function testOfferingPricingTypeMigrationExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-products/migrations/0003_offering_pricing.sql';
        $this->assertFileExists($path, 'Offering pricing-type migration must exist (hourly/fixed/per_unit/quote_required).');
        $sql = file_get_contents($path);
        $this->assertStringContainsString("ENUM('hourly','fixed','per_unit','quote_required')", $sql, 'pricing_type ENUM must allow all four modes.');
        $this->assertStringContainsString('unit_label', $sql, 'unit_label column must exist for per_unit pricing.');
        $this->assertStringContainsString('latitude', $sql, 'service_areas must get a latitude column for geo discovery.');
        $this->assertStringContainsString('longitude', $sql, 'service_areas must get a longitude column for geo discovery.');
        $this->assertStringContainsString('radius_km', $sql, 'service_areas must get a radius_km column.');
    }

    public function testVendorsRepositoryHasProfileAndAreaMethods(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/src/Repository.php';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertStringContainsString('public function updateProfile', $src, 'Vendors repo must expose updateProfile().');
        $this->assertStringContainsString('public function createLocation', $src, 'Vendors repo must expose createLocation().');
        $this->assertStringContainsString('public function createServiceArea', $src, 'Vendors repo must expose createServiceArea().');
        $this->assertStringContainsString('mercato_vendor_locations', $src, 'createLocation must write to vendor_locations.');
        $this->assertStringContainsString('mercato_service_areas', $src, 'createServiceArea must write to service_areas.');
    }

    public function testVendorsProviderRegistersGeoAndSignupRoutes(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/src/Provider.php';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertStringContainsString('/locations', $src, 'POST /vendors/{id}/locations must be registered.');
        $this->assertStringContainsString('/service-areas', $src, 'POST /vendors/{id}/service-areas must be registered.');
        $this->assertStringContainsString('/profile', $src, 'PATCH /vendors/{id}/profile must be registered.');
        $this->assertStringContainsString('/storefront/signup', $src, 'POST /storefront/signup orchestrator must be registered.');
    }

    public function testSignupOrchestratorExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/src/Signup.php';
        $this->assertFileExists($path, 'Signup orchestrator must exist to translate the multi-step storefront form into vendor + location + area + service rows.');
        $src = file_get_contents($path);
        $this->assertStringContainsString('public function run', $src, 'Signup::run() is the public entrypoint.');
        $this->assertStringContainsString('createLocation', $src, 'Signup must call createLocation when lat/lng is provided.');
        $this->assertStringContainsString('createServiceArea', $src, 'Signup must call createServiceArea for declared zones.');
        $this->assertStringContainsString('wp_create_user', $src, 'Signup must create a WP user for new applicants.');
    }

    public function testStorefrontRendererServesSignupAndGeo(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Storefront/Renderer.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString("'/signup'", $src, '/signup route must be wired in the storefront renderer.');
        $this->assertStringContainsString('renderSignup', $src, 'renderSignup() must exist on the renderer.');
        $this->assertStringContainsString('resolveGeoQuery', $src, 'Geo query helper must be wired to /services and /providers.');
        $this->assertStringContainsString('Geocoder', $src, 'Renderer must depend on the Geocoder helper.');
    }

    public function testGeocoderHelperExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Geo/Geocoder.php';
        $this->assertFileExists($path, 'Geocoder helper must exist.');
        $src = file_get_contents($path);
        $this->assertStringContainsString('public function geocode', $src, 'Geocoder must expose geocode($query).');
        $this->assertStringContainsString('public static function distanceKm', $src, 'Geocoder must expose distanceKm() for Haversine math.');
        $this->assertStringContainsString('nominatim', strtolower($src), 'Geocoder should default to OSM Nominatim.');
    }

    public function testStorefrontRepositoryAppliesGeoFilter(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Storefront/Repository.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('applyGeoFilter', $src, 'Storefront repo must implement geo filter.');
        $this->assertStringContainsString('distance_km', $src, 'Geo filter must annotate results with distance_km.');
        $this->assertStringContainsString('serves_area', $src, 'Geo filter must annotate results with serves_area flag.');
        $this->assertStringContainsString('signupPage', $src, 'Storefront repo must expose signupPage() for the multi-step form.');
        $this->assertStringContainsString('service_areas', $src, 'providerDetail() must surface service_areas to the template.');
    }

    public function testSignupTemplateHasFiveSteps(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/signup-page.php';
        $this->assertFileExists($path, 'signup-page.php template must exist.');
        $src = file_get_contents($path);
        foreach (['data-step="1"', 'data-step="2"', 'data-step="3"', 'data-step="4"', 'data-step="5"'] as $marker) {
            $this->assertStringContainsString($marker, $src, "Signup form must render step $marker.");
        }
        $this->assertStringContainsString('navigator.geolocation', $src, 'Signup must offer browser geolocation.');
        $this->assertStringContainsString('/wp-json/mercato/v1/storefront/signup', $src, 'Signup must POST to the storefront signup REST endpoint.');
    }

    public function testProviderDetailSurfacesMultiServiceAndAreas(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/provider-detail.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('service_areas', $src, 'Provider detail must show service areas.');
        $this->assertStringContainsString('pricing_type', $src, 'Provider detail must reflect the offering pricing type.');
        $this->assertStringContainsString('trust-list', $src, 'Provider detail must include the trust/safety panel.');
        $this->assertStringContainsString('portfolio', $src, 'Provider detail must include a portfolio gallery.');
    }

    public function testBidLimitsEnforcedInServiceOps(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/src/Repository.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('enforceBidLimits', $src, 'Bid throttling helper must be wired into createBid.');
        $this->assertStringContainsString('BID_LIMIT_PER_REQUEST', $src, 'Per-request limit code must be raised when exceeded.');
        $this->assertStringContainsString('BID_LIMIT_DAILY', $src, 'Daily-per-vendor limit code must be raised when exceeded.');
        $this->assertStringContainsString('BID_LIMIT_COOLDOWN', $src, 'Cooldown-between-bids code must be raised when exceeded.');
        $this->assertStringContainsString("'bidding.daily_bid_limit_per_vendor'", $src, 'Daily limit key must match enterprise seed key.');
    }

    public function testEnterpriseSeedsBiddingLimits(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Repository.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('bidding.daily_bid_limit_per_vendor', $src, 'Enterprise seedStarterFlags must set the daily bid limit default.');
        $this->assertStringContainsString('bidding.max_bids_per_request', $src, 'Enterprise seedStarterFlags must set the per-request bid limit default.');
        $this->assertStringContainsString('bidding.min_seconds_between_bids', $src, 'Enterprise seedStarterFlags must set the bid-cooldown default.');
    }

    public function testHeaderHasBecomeAProCTA(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/partials/header.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('Become a Pro', $src, 'Header must promote the provider signup flow.');
        $this->assertStringContainsString('/signup', $src, 'Header CTA must link to /signup.');
    }

    public function testServicesPageHasGeoSearch(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/services-page.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('name="near"', $src, 'Services page must expose the geo near= input.');
        $this->assertStringContainsString('name="radius"', $src, 'Services page must expose the radius= input.');
        $this->assertStringContainsString('navigator.geolocation', $src, 'Services page must wire browser geolocation.');
        $this->assertStringContainsString('pricing_type', $src, 'Services page must reflect per-offering pricing type.');
    }

    public function testProvidersPageHasGeoSearch(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/providers-page.php';
        $src = file_get_contents($path);
        $this->assertStringContainsString('name="near"', $src, 'Providers page must expose the geo near= input.');
        $this->assertStringContainsString('avg_rating', $src, 'Providers page must show avg rating.');
        $this->assertStringContainsString('background_check_status', $src, 'Providers page must show background-check badge.');
    }
}
