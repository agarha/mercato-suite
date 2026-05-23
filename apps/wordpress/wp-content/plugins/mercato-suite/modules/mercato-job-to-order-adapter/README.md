# mercato-job-to-order-adapter

Bridges an awarded Mercato bid/job to a WooCommerce order so the buyer
has a payable checkout entry.

This adapter is the **only** place in the suite that may call
`wc_create_order()` (CODEX_DIRECTIVE.md §5.1).

## Boundary

- WooCommerce owns: cart, checkout, payment gateway, payment state.
- This adapter owns: minting the order shell with `mercato_job_id` and
  `tenant_id` line-item meta so downstream WC hooks can be correlated
  back to the Mercato job.

## Hook subscriptions

None. The adapter is invoked directly by the bid-award use-case in
`mercato-orders` (or whichever module finalises awards).

## Events

- Emits `mercato.adapter.job_to_order.created.v1` on success.
- Emits `mercato.adapter.job_to_order.failed.v1` so the award flow can
  compensate (refund hold, re-open bidding, etc.).

## Forbidden

This adapter must not:

- Call any payment-gateway API.
- Mutate cart/session state.
- Reach into WC checkout flow.

It mints the order shell and returns the order ID. Everything else is
WC's job.
