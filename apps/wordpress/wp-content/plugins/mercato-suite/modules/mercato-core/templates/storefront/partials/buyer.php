<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $money */ ?>
<section class="section" id="buyer">
  <div class="section-head">
    <div><h2><?= $esc($config['buyer_headline']) ?></h2><p><?= $esc($config['buyer_copy']) ?></p></div>
  </div>
  <div class="user-grid">
    <div class="cart-panel">
      <h3>Checkout preview</h3>
      <div class="cart-line"><span>Cart contains products from multiple vendors</span><strong>Split after payment</strong></div>
      <div class="cart-line"><span>Tax, shipping, discounts</span><strong>Allocated by suborder</strong></div>
      <div class="cart-line"><span>Payment</span><strong>Stripe test intent</strong></div>
      <div class="cart-line"><span>Refund support</span><strong>Commission reversal</strong></div>
    </div>
    <div class="account-panel">
      <h3>Buyer order history</h3>
      <table class="table">
        <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Refunded</th><th>Tracking</th></tr></thead>
        <tbody>
          <?php if (empty($data['orders'])): ?>
            <tr><td colspan="6">No order records yet.</td></tr>
          <?php else: foreach ($data['orders'] as $order): ?>
            <tr>
              <td>#<?= $esc($order['wc_order_id']) ?></td>
              <td><?= $esc($order['status']) ?></td>
              <td><?= $esc($order['payment_status']) ?></td>
              <td><?= $money($order['total_minor']) ?></td>
              <td><?= $money($order['refunded_minor']) ?></td>
              <td><?= $esc($order['tracking_carrier']) ?> <?= $esc($order['tracking_number']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
