<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
$provider = $data['provider'];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $esc($provider['business_name']) ?> — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="<?= $attr($provider['business_name']) ?> on <?= $attr($config['brand']) ?>.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css') ?>">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero">
        <div>
          <div class="eyebrow">Verified provider</div>
          <h1><?= $esc($provider['business_name']) ?></h1>
          <p>@<?= $esc($provider['store_slug']) ?> · <?= $provider['stripe_account_id'] ? 'Connected to Stripe payouts' : 'Payouts pending' ?> · Status: <?= $esc($provider['status']) ?></p>
          <div class="hero-actions">
            <a class="button" href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/requests/new?provider=<?= $attr($provider['store_slug']) ?>">Request a quote</a>
            <a class="button secondary" href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/providers">All providers</a>
          </div>
        </div>
        <aside class="hero-media">
          <div class="booking-panel">
            <h3>About this provider</h3>
            <div class="cart-line"><span>Services offered</span><strong><?= count($data['services']) ?></strong></div>
            <div class="cart-line"><span>Recent jobs</span><strong><?= count($data['recent_jobs']) ?></strong></div>
            <div class="cart-line"><span>Payout method</span><strong><?= $provider['stripe_account_id'] ? 'Stripe Connect' : 'Pending' ?></strong></div>
          </div>
        </aside>
      </section>
    </div>

    <section class="section">
      <div class="section-head"><div><h2>Services from this provider</h2></div></div>
      <div class="product-grid">
        <?php if (empty($data['services'])): ?>
          <article class="empty-state"><h3>No services published yet</h3><p>This provider hasn't published bookable services.</p></article>
        <?php else: foreach ($data['services'] as $index => $service):
          $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4]; ?>
          <article class="product-card">
            <div class="product-media <?= $tone ?>"><span><?= $esc(mb_substr((string) $service['title'], 0, 1)) ?></span></div>
            <div class="product-body">
              <h3><?= $esc($service['title']) ?></h3>
              <p><?= $esc($service['description'] ?: $config['item_fallback_copy']) ?></p>
              <div class="product-meta"><strong><?= $money($service['price_minor']) ?></strong><span><?= $esc($service['stock_quantity']) ?> <?= $esc($config['item_quantity_label']) ?></span></div>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="section">
      <div class="section-head"><div><h2>Recent jobs</h2></div></div>
      <div class="account-panel">
        <table class="table">
          <thead><tr><th>Job</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
            <?php if (empty($data['recent_jobs'])): ?>
              <tr><td colspan="3">No jobs yet.</td></tr>
            <?php else: foreach ($data['recent_jobs'] as $job): ?>
              <tr><td>#<?= $esc($job['job_id']) ?></td><td><?= $esc($job['status']) ?></td><td><?= $esc($job['updated_at']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
