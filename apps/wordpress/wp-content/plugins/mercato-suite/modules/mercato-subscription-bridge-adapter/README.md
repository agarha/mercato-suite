# mercato-subscription-bridge-adapter

Maps WooCommerce Subscriptions cadence onto Mercato recurring service
jobs.

## Why

Per CODEX_DIRECTIVE.md §5.2 Mercato must NOT build a custom
subscription cadence or dunning engine. WC Subscriptions (or any
compatible plugin) owns billing cadence, renewal retries, and dunning.
Mercato just listens for renewal/cancellation signals and spins up the
next service job for the provider.

If WC Subscriptions is not active, the adapter no-ops cleanly.

## Hook subscriptions (canonical, per CODEX_DIRECTIVE.md §4)

| WC hook | Method | What we do |
|---|---|---|
| `woocommerce_subscription_status_updated` | `onStatusUpdated` | Emit status change + (if cancelled/expired) emit `cancelled` |
| `woocommerce_subscription_renewal_payment_complete` | `onRenewalPaymentComplete` | Emit `renewed` so the next job spins up |

## Meta contract

A subscription opts into the bridge by carrying these meta keys
(written when the original recurring service is booked):

- `_mercato_tenant_id`             (int, required)
- `_mercato_recurring_service_id`  (int, required)
- `_mercato_provider_id`           (int, optional — set on award)

Absent required meta = not Mercato-managed; adapter ignores.

## Events

- `mercato.adapter.subscription_bridge.renewed.v1`
- `mercato.adapter.subscription_bridge.cancelled.v1`
- `mercato.adapter.subscription_bridge.status_changed.v1`
