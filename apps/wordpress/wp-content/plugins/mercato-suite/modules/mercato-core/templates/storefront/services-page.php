<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
/** @var string $current_page */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Services — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Browse every service offered on <?= $attr($config['brand']) ?>.">
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
          <div class="eyebrow">Service catalog</div>
          <h1>Every service available right now</h1>
          <p>Browse approved providers, filter by category, and request quotes — all backed by tenant-scoped data.</p>
        </div>
      </section>
    </div>

    <section class="section">
      <div class="section-head">
        <div><h2><?= count($data['services']) ?> active services</h2><p>Each service is offered by a verified provider on this tenant.</p></div>
        <span class="pill"><?= count($data['categories']) ?> categories</span>
      </div>

      <div class="subcategory-cloud">
        <?php foreach ($data['categories'] as $category): ?>
          <span><?= $esc($category['name']) ?></span>
        <?php endforeach; ?>
      </div>

      <div class="product-grid" style="margin-top:18px">
        <?php if (empty($data['services'])): ?>
          <article class="empty-state"><h3>No services yet</h3><p>Providers will appear here once they publish offerings.</p></article>
        <?php else: foreach ($data['services'] as $index => $service):
          $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4]; ?>
          <article class="product-card">
            <div class="product-media <?= $tone ?>"><span><?= $esc(mb_substr((string) $service['title'], 0, 1)) ?></span><small>Verified provider</small></div>
            <div class="product-body">
              <p class="vendor-name"><a href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/providers/<?= $attr($service['store_slug']) ?>"><?= $esc($service['business_name']) ?></a></p>
              <h3><?= $esc($service['title']) ?></h3>
              <p><?= $esc($service['description'] ?: $config['item_fallback_copy']) ?></p>
              <div class="product-meta">
                <strong><?= $money($service['price_minor']) ?></strong>
                <span><?= $esc($service['stock_quantity']) ?> <?= $esc($config['item_quantity_label']) ?></span>
              </div>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
