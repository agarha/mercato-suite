# WooCommerce Boundary And Adapter Strategy

Mercato is not a WooCommerce replacement. Mercato is the multi-tenant SaaS services-marketplace operations layer on top of WordPress and WooCommerce.

## Ownership

WooCommerce and selected WooCommerce plugins own commerce mechanics:

- cart and checkout
- customer accounts
- buyer-facing payment state
- payment gateway UI and initial charge processing
- coupons
- tax calculation through WooCommerce or tax plugins
- subscription cadence through WooCommerce Subscriptions or a compatible plugin
- refund payment records
- admin shell, sessions, nonces, and cron

Mercato owns services-marketplace and SaaS primitives:

- tenant routing and tenant-scoped data
- provider lifecycle and approval
- provider service areas and operational status
- service templates
- client service requests
- provider bids and auction/award rules
- jobs and dispatch lifecycle
- commission rules
- provider payout orchestration
- audit log and event outbox
- Xusmo tenant provisioning and integration surface

## Adapter Rules

Mercato should connect to WooCommerce through thin adapters only:

- `JobToOrder`: award a service bid and create/link the WooCommerce order payment record.
- `OrderToJob`: react to WooCommerce payment completion and advance the Mercato job state.
- `RefundToJob`: react to WooCommerce refunds and reverse commission/payout obligations.
- `SubscriptionToRecurringService`: map WooCommerce subscription renewals to recurring service jobs.
- `CouponContext`: pass tenant/provider/job context into WooCommerce coupon validation.
- `TaxContext`: pass service location into WooCommerce/tax-plugin calculation and consume the result.
- `StripeConnect`: manage provider Connect accounts and transfers only; initial charge remains WooCommerce gateway work.

## Forbidden Drift

Do not implement Mercato-owned versions of:

- carts
- checkout
- payment gateways
- custom payment-intent systems for the initial buyer charge
- tax engines
- coupon engines
- subscription cadence or dunning engines
- customer account records
- custom sessions, nonces, or cron

The drift rules are enforced by the grep checks in [CODEX_DIRECTIVE.md](../../CODEX_DIRECTIVE.md).

## Local Plugin State

The local Docker WordPress site currently has:

- `mercato-suite`
- `woocommerce`
- `woocommerce-paypal-payments`
- `woocommerce-gateway-stripe` pinned locally to a WordPress 6.5-compatible version

These plugins are runtime dependencies/adapters, not Mercato source modules. Mercato source should depend on them through documented adapter seams rather than reimplementing their commerce mechanics.

## Current Drift Audit

Most directive greps are clean. One known allowed vocabulary-debt match exists:

- `mercato-products/src/Repository.php` writes `_mercato_vendor_id` to WooCommerce product post meta.

That write is an allowed bridge metadata key under the directive exception. It should be renamed to provider/service vocabulary in a coordinated future rename pass, not as part of unrelated feature work.
