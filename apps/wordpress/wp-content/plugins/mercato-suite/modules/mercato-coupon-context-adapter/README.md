# mercato-coupon-context-adapter

Restricts WooCommerce coupon validity to the current tenant / provider
context. Mercato does NOT implement its own coupon engine.

## Boundary

- WooCommerce owns: coupon storage, percent/fixed math, application
  to cart items.
- This adapter owns: filtering "is this coupon valid right now?" so a
  tenant-scoped or provider-scoped coupon never applies outside its
  scope.

## Hook subscriptions (canonical, per CODEX_DIRECTIVE.md §4)

| WC hook | Method | What we do |
|---|---|---|
| `woocommerce_coupon_is_valid` | `filterIsValid` | Return false when tenant/provider mismatch |

## Meta contract

Coupons opt into scope by storing post meta on the `shop_coupon` post:

- `_mercato_tenant_id`   (int) → restrict to tenant
- `_mercato_provider_id` (int) → restrict to provider

Absent meta = global coupon (default WC behaviour).

## Events

- `mercato.adapter.coupon_context.evaluated.v1` — emitted on every
  scope evaluation so analytics can chart coupon coverage.
