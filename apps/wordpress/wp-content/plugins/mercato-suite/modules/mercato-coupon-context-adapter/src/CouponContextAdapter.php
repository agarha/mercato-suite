<?php

declare(strict_types=1);

namespace Mercato\Adapters\CouponContext;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;

/**
 * Filter WC coupon validation by tenant / provider / job context.
 *
 * Coupons in Mercato can be:
 *   - global       (no restriction)
 *   - tenant-scoped (meta: _mercato_tenant_id)
 *   - provider-scoped (meta: _mercato_provider_id)
 *
 * A coupon stays valid only when the request context matches the
 * coupon's scope. The actual discount calculation remains 100 % WC.
 */
final class CouponContextAdapter
{
    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    /**
     * @param bool   $isValid current WC validity verdict
     * @param mixed  $coupon  WC_Coupon
     * @param mixed  $discounts WC_Discounts
     */
    public function filterIsValid(bool $isValid, $coupon, $discounts = null): bool
    {
        if (!$isValid) {
            return false;
        }

        if (!\is_object($coupon) || !\method_exists($coupon, 'get_meta')) {
            return $isValid;
        }

        $tenantId = $this->tenants->currentTenantId();
        $couponTenant = (int) $coupon->get_meta('_mercato_tenant_id');
        $couponProvider = (int) $coupon->get_meta('_mercato_provider_id');

        $code = \method_exists($coupon, 'get_code') ? (string) $coupon->get_code() : '';
        $reason = 'matched';
        $valid = true;

        if ($couponTenant > 0 && $tenantId !== null && $couponTenant !== $tenantId) {
            $valid = false;
            $reason = 'tenant_mismatch';
        }

        // Provider scope is checked against context if downstream sets a hint;
        // we never read WC cart internals here. If a provider hint is not
        // available, we treat provider-scoped coupons as not-applicable.
        if ($valid && $couponProvider > 0 && !$this->matchesProvider($couponProvider)) {
            $valid = false;
            $reason = 'provider_mismatch';
        }

        $payload = [
            'coupon_code' => $code,
            'tenant_id' => $tenantId,
            'coupon_tenant_id' => $couponTenant,
            'coupon_provider_id' => $couponProvider,
            'valid' => $valid,
            'reason' => $reason,
        ];

        $this->outbox->publish('mercato.adapter.coupon_context.evaluated.v1', $payload, $tenantId ?? 0);
        $this->audit->log('adapter.coupon_context.evaluated', 'coupon', 0, null, $payload);

        return $valid;
    }

    /**
     * Provider-scope match. Hint is taken from the global request bag set
     * by mercato-orders during checkout context build-up. If no hint is
     * available we err on the side of "not applicable" so a provider
     * coupon never leaks across providers.
     */
    private function matchesProvider(int $couponProviderId): bool
    {
        $hint = isset($GLOBALS['mercato_request_provider_id'])
            ? (int) $GLOBALS['mercato_request_provider_id']
            : 0;
        return $hint > 0 && $hint === $couponProviderId;
    }
}
