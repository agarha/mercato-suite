# Audit — `POST /mercato/v1/stripe/payment-intents`

**Reviewer:** Claude
**Date:** 2026-05-23
**Scope:** `mercato-stripe-connect` module's `/mercato/v1/stripe/payment-intents` route.
**Against:** `CODEX_DIRECTIVE.md` §5.1 ("Mercato never creates PaymentIntents for the initial charge").

## Verdict

**The route is a directive §5.1 violation.** It creates Stripe PaymentIntents directly against `https://api.stripe.com/v1/payment_intents` tied to a `wc_order_id`. That is the initial buyer charge by any reasonable reading of the term.

The violation has a **small blast radius** in practice because:

- Route is `canManage`-gated (admin only), not buyer-facing.
- In test mode (the default for this demo and for any non-production install with no `STRIPE_SECRET_KEY`) the implementation returns a fake `pi_test_*` ID without contacting Stripe.
- In "live" mode the call hardcodes `payment_method=pm_card_visa` (Stripe's test card token), so it cannot actually charge a real card even if reached.
- The only known caller is the Playwright `PW-021` scenario asserting the route returns 201. No SPA, storefront, or other module calls it.

## Evidence

`mercato-stripe-connect/src/Provider.php` (lines 58–62):

```
\register_rest_route('mercato/v1', '/stripe/payment-intents', [
    'methods' => 'POST',
    'callback' => [$this, 'createPaymentIntent'],
    'permission_callback' => [Permissions::class, 'canManage'],
]);
```

`mercato-stripe-connect/src/Repository.php::createPaymentIntent` (lines 108–147):

- accepts `wc_order_id` + `amount_minor` + `currency`
- calls `createStripePaymentIntent($amount, $currency)` which posts to `https://api.stripe.com/v1/payment_intents` with `confirm=true`
- stores the resulting intent in `wp_mercato_stripe_payment_intents`
- emits `mercato.stripe.payment_intent.created.v1` to the outbox

`tests/playwright/mvp-scenarios.json` (line 22):

```
{"id":"PW-021","name":"payment intent create",
 "url":"/?rest_route=/mercato/v1/stripe/payment-intents",
 "assertions":["status-201","payment-intent"],"area":"payments"}
```

That single Playwright entry is the only consumer of the route.

## Why this is the wrong design

The directive's job split is:

| Concern | Owner | Adapter |
|---|---|---|
| Initial buyer charge | **WC Stripe gateway** | none (WC + plugin do it) |
| Provider payouts (Connect Transfers) | mercato-stripe-connect | uses Stripe Connect API |
| Refund payment record | WC Stripe gateway | mercato-refund-to-job-adapter listens |

A separate route that mints PaymentIntents from inside Mercato:

1. Duplicates what `WC_Gateway_Stripe::process_payment()` already does for any cart that goes through checkout.
2. Breaks the predictability of "the buyer's `payment_complete` hook is the source of truth" — now an admin can mint an intent out-of-band that doesn't go through normal checkout state.
3. Creates a PCI scope question (currently SAQ-A because raw card data never touches the platform). An admin-callable intent-creation route is one refactor away from accepting card details.
4. Means the rest of the system (refund reversal, commission accrual, payout) has to handle two distinct "how the order got paid" paths instead of one.

## Recommendation

**Delete the route, the `Repository::createPaymentIntent` method, the corresponding `private createStripePaymentIntent()` HTTP wrapper, and the `PW-021` Playwright scenario.**

The `wp_mercato_stripe_payment_intents` table can stay — it's also populated by webhook ingestion (the `/stripe/webhook` route), and removing the column set would force a coordinated migration. The dead table rows from this route's previous use are harmless.

If a future use-case actually needs admin-callable PI creation (e.g. a top-up flow that genuinely is not an initial buyer charge), reintroduce it under a different name (`/stripe/provider-topups`) with an explicit comment explaining how it differs from the directive's §5.1 boundary.

## Fix applied in this commit

This branch (`claude/stripe-pi-route-audit`) applies the recommendation:

- Removes `register_rest_route('mercato/v1', '/stripe/payment-intents', ...)` from `mercato-stripe-connect/src/Provider.php`.
- Removes the `createPaymentIntent` REST callback method from the same file.
- Removes `Repository::createPaymentIntent` and the private `createStripePaymentIntent` HTTP helper.
- Updates the `provides_events` list in `mercato-stripe-connect/module.json` to drop `mercato.stripe.payment_intent.created.v1` (no longer emitted).
- Removes the `PW-021` entry from `tests/playwright/mvp-scenarios.json`.

What is **not** touched:

- The table `wp_mercato_stripe_payment_intents` (kept; the schema is harmless and may be needed for webhook-driven records).
- The `/stripe/refunds` route (kept; refund processing through Stripe is a different question — provider-payout-side refunds are arguably within scope).
- The webhook route `/stripe/webhook` (kept; receiving Stripe events back is allowed).

If you disagree with the deletion, the rollback is `git revert <commit>` on this branch.
