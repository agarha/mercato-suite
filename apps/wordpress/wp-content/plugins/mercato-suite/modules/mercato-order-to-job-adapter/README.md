# mercato-order-to-job-adapter

Reflects WooCommerce order lifecycle events into Mercato job state.

## Boundary

- WooCommerce owns: payment processing, order status transitions.
- This adapter owns: observing those transitions and emitting Mercato
  events so jobs/commissions/notifications stay in sync.

## Hook subscriptions (canonical, per CODEX_DIRECTIVE.md §4)

| WC hook | Method | What we do |
|---|---|---|
| `woocommerce_new_order` | `onNewOrder` | Emit `linked` event |
| `woocommerce_payment_complete` | `onPaymentComplete` | Emit `paid` event |
| `woocommerce_order_status_changed` | `onStatusChanged` | Emit `status_synced` event |

No other WC hooks. Adding one requires a `contract-change` PR.

## Events

- `mercato.adapter.order_to_job.linked.v1`
- `mercato.adapter.order_to_job.paid.v1`
- `mercato.adapter.order_to_job.status_synced.v1`

Job state transitions are owned by `mercato-jobs` / `mercato-orders`.
Commission accrual is owned by `mercato-commissions`. Provider
notification is owned by `mercato-notifications`. Each subscribes to
the events above.
