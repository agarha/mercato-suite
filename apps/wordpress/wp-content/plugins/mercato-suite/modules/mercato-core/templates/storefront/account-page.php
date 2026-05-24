<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your account — <?= $esc($config['brand']) ?></title>
  <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css') ?>">
  <?php if (($theme ?? '') === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css') ?>"><?php endif; ?>
</head>
<body<?= ($theme ?? '') === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero">
        <div>
          <div class="eyebrow">Your account</div>
          <?php if ((int) $data['user_id'] <= 0): ?>
            <h1>Sign in to see your account</h1>
            <p>Bookings, paid jobs, refunds, and your active service requests will show up here.</p>
            <div class="hero-actions">
              <a class="button" href="/wp-login.php?redirect_to=<?= $attr('/t/' . ($config['tenant_slug'] ?? 'gigsii') . '/account') ?>">Sign in</a>
              <a class="button secondary" href="/wp-login.php?action=register">Create an account</a>
            </div>
          <?php else: ?>
            <h1>Welcome back</h1>
            <p><?= count($data['orders']) ?> orders, <?= count($data['requests']) ?> open requests.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <?php if ((int) $data['user_id'] > 0): ?>
      <section class="section">
        <div class="section-head"><div><h2>Order history</h2></div></div>
        <div class="account-panel">
          <table class="table">
            <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Refunded</th><th>Tracking</th></tr></thead>
            <tbody>
              <?php if (empty($data['orders'])): ?>
                <tr><td colspan="6">No orders yet.</td></tr>
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
      </section>

      <section class="section">
        <div class="section-head"><div><h2>Your service requests</h2></div></div>
        <div class="account-panel">
          <table class="table">
            <thead><tr><th>Request</th><th>Title</th><th>Location</th><th>Mode</th><th>Budget</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($data['requests'])): ?>
                <tr><td colspan="6">No requests yet. <a href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/requests/new">Post one →</a></td></tr>
              <?php else: foreach ($data['requests'] as $req):
                $loc = trim((string) ($req['city'] ?? '') . ', ' . (string) ($req['region'] ?? ''), ' ,'); ?>
                <tr>
                  <td>#<?= $esc($req['request_id']) ?></td>
                  <td><?= $esc($req['title']) ?></td>
                  <td><?= $esc($loc === '' ? 'Remote/local' : $loc) ?></td>
                  <td><?= $esc($req['bid_mode']) ?></td>
                  <td><?= $money($req['budget_max_minor']) ?> <?= $esc($req['currency']) ?></td>
                  <td><?= $esc($req['status']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
