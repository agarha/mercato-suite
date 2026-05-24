<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Providers — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Approved providers on <?= $attr($config['brand']) ?>.">
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
          <div class="eyebrow">Provider directory</div>
          <h1>Approved providers in your area</h1>
          <p>Every provider has been verified, KYC'd, and connected to payouts. Click through to see services, ratings, and recent work.</p>
        </div>
      </section>
    </div>

    <section class="section">
      <div class="section-head">
        <div><h2><?= count($data['providers']) ?> active providers</h2></div>
        <span class="pill">KYC + Stripe Connect</span>
      </div>

      <div class="vendor-grid">
        <?php if (empty($data['providers'])): ?>
          <article class="empty-state"><h3>No providers yet</h3><p>Provider applications will appear here after approval.</p></article>
        <?php else: foreach ($data['providers'] as $provider): ?>
          <article class="vendor-card">
            <div class="vendor-avatar"><?= $esc(mb_substr((string) $provider['business_name'], 0, 1)) ?></div>
            <div>
              <h3><a href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/providers/<?= $attr($provider['store_slug']) ?>"><?= $esc($provider['business_name']) ?></a></h3>
              <p>@<?= $esc($provider['store_slug']) ?> · <?= (int) $provider['service_count'] ?> services · <?= (int) $provider['job_count'] ?> jobs</p>
              <span><?= $esc($provider['status']) ?> · <?= $provider['stripe_account_id'] ? 'Stripe connected' : 'Payouts pending' ?></span>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
