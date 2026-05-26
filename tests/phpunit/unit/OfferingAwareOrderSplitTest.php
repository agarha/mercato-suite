<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OfferingAwareOrderSplitTest extends TestCase
{
    private string $splitter;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->splitter = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-orders/src/Splitter.php') ?: '';
    }

    public function testOrderSplitterPrefersOfferingMetadataBeforeLegacyVendorMeta(): void
    {
        self::assertStringContainsString('offeringIdForItem', $this->splitter);
        self::assertStringContainsString('vendorIdForOffering', $this->splitter);
        self::assertStringContainsString('_mercato_vendor_id', $this->splitter);

        $offeringPosition = strpos($this->splitter, 'vendorIdForOffering');
        $legacyPosition = strpos($this->splitter, '_mercato_vendor_id');

        self::assertIsInt($offeringPosition);
        self::assertIsInt($legacyPosition);
        self::assertLessThan($legacyPosition, $offeringPosition);
    }

    public function testSuborderItemsPersistOfferingId(): void
    {
        self::assertStringContainsString("'offering_id' => \$offeringId > 0 ? \$offeringId : null", $this->splitter);
        self::assertStringContainsString("'offering_id' => \$item['offering_id']", $this->splitter);
    }

    public function testOfferingLookupIsTenantScopedAndProductBound(): void
    {
        self::assertStringContainsString('mercato_vendor_service_offerings', $this->splitter);
        self::assertStringContainsString('o.`tenant_id` = %d', $this->splitter);
        self::assertStringContainsString('p.`wc_product_id` = %d', $this->splitter);
        self::assertStringContainsString("o.`status` = 'active'", $this->splitter);
    }
}
