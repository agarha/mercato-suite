# mercato-refund-to-job-adapter

Bridges WooCommerce refund events into Mercato so commission accruals
and provider payout obligations get reversed.

## Boundary

- WooCommerce owns: the refund record + processing the refund through
  the payment gateway.
- This adapter owns: emitting Mercato events so downstream ledger
  modules can reverse what they previously accrued.

## Hook subscriptions (canonical, per CODEX_DIRECTIVE.md §4)

| WC hook | Method | What we do |
|---|---|---|
| `woocommerce_refund_created` | `onRefundCreated` | Emit `reversed` event |
| `woocommerce_order_refunded` | `onOrderRefunded` | Emit `post_refund` event |

## Events

- `mercato.adapter.refund_to_job.reversed.v1` — commission/payout reversal
- `mercato.adapter.refund_to_job.post_refund.v1` — post-refund side effects

## Consumers

- `mercato-commissions` writes the reversal journal entry.
- `mercato-payouts` adjusts the open payout balance.
- `mercato-notifications` fires the refund notice.
