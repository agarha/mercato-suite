# mercato-tax-context-adapter

Passes service-delivery location into the WC tax flow. Mercato does NOT
compute tax — that is WC core + the configured tax plugin (TaxJar,
Avalara, etc.).

## Why this is needed

For physical goods, WC infers jurisdiction from the buyer's shipping
address. For services, the correct jurisdiction is the **service
location** — where the job is performed — which is usually different
from the buyer's billing address.

This adapter exposes the service location to WC's tax engine through
two seams:

1. A request-scoped global (`$GLOBALS['mercato_service_location']`) set
   by `mercato-orders` / `mercato-bids` when the buyer picks a service
   slot.
2. Order line-item meta written at checkout creation, so post-payment
   tax recalculation has the same context.

## Boundary

- WC + tax plugins own: rate lookup, brackets, exemptions, certificates.
- This adapter owns: handing them the right location.

## Hook subscriptions (canonical, per CODEX_DIRECTIVE.md §4)

| WC hook | Method | What we do |
|---|---|---|
| `woocommerce_cart_calculate_fees` | `onCartCalculateFees` | Emit `applied` event (read-only) |
| `woocommerce_checkout_create_order` | `onCheckoutCreateOrder` | Stamp `_mercato_service_*` meta |

## Events

- `mercato.adapter.tax_context.applied.v1`
