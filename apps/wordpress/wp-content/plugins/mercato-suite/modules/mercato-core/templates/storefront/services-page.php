<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
/** @var string $current_page */
$search_q = (string) ($search_q ?? '');
$search_category = (int) ($search_category ?? 0);
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$resultCount = count($data['services']);
$hasFilter = ($search_q !== '' || $search_category > 0);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Services<?= $hasFilter ? ' — ' . $esc($search_q ?: '') : '' ?> — <?= $esc($config['brand']) ?></title>
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
      <section class="hero" aria-labelledby="svc-heading">
        <div>
          <div class="eyebrow">Service catalog</div>
          <h1 id="svc-heading"><?= $hasFilter ? 'Search results' : 'Every service available right now' ?></h1>
          <p><?= $hasFilter
              ? $resultCount . ' service' . ($resultCount === 1 ? '' : 's') . ' matching ' . ($search_q !== '' ? '"' . $esc($search_q) . '"' : 'your category')
              : 'Browse approved providers, filter by category, and request quotes — all backed by tenant-scoped data.' ?></p>
        </div>
        <aside class="hero-media" aria-label="Search and filter">
          <div class="booking-panel" role="search" aria-label="Filter services">
            <h3>Find a service</h3>
            <form class="search-row" action="<?= $attr($home . '/services') ?>" method="get" novalidate style="grid-template-columns: 1fr 1fr auto">
              <label class="field" for="svc-q">
                <span>Keywords</span>
                <input id="svc-q" name="q" type="search" value="<?= $attr($search_q) ?>" placeholder="cleaning, repairs, install…" autocomplete="off" style="display:block;width:100%;border:0;background:transparent;font:inherit;color:var(--ink);outline:none;margin-top:4px;padding:0">
              </label>
              <label class="field" for="svc-cat">
                <span>Category</span>
                <select id="svc-cat" name="category" style="display:block;width:100%;border:0;background:transparent;font:inherit;color:var(--ink);outline:none;margin-top:4px;padding:0">
                  <option value="0">Any category</option>
                  <?php foreach ($data['categories'] as $category): ?>
                    <option value="<?= $attr($category['category_id']) ?>"<?= $search_category === (int) $category['category_id'] ? ' selected' : '' ?>><?= $esc($category['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="search-btn">Search</button>
            </form>
            <?php if ($hasFilter): ?>
              <p style="margin:12px 0 0;font-size:13px;color:var(--muted)">
                <a href="<?= $attr($home . '/services') ?>" style="color:var(--brand-deep);font-weight:600">Clear filters</a>
              </p>
            <?php endif; ?>
          </div>
        </aside>
      </section>
    </div>

    <section class="section">
      <div class="section-head">
        <div>
          <h2><?= $resultCount ?> active service<?= $resultCount === 1 ? '' : 's' ?></h2>
          <p>Each service is offered by a verified provider on this tenant.</p>
        </div>
        <span class="pill"><?= count($data['categories']) ?> categories</span>
      </div>

      <div class="product-grid">
        <?php if (empty($data['services'])): ?>
          <article class="empty-state">
            <h3>No services match<?= $search_q !== '' ? ' "' . $esc($search_q) . '"' : '' ?></h3>
            <p>Try a different keyword or <a href="<?= $attr($home . '/services') ?>">clear filters</a> to see the full catalog.</p>
          </article>
        <?php else: foreach ($data['services'] as $index => $service):
          $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4]; ?>
          <article class="product-card">
            <div class="product-media <?= $tone ?>"><span><?= $esc(mb_substr((string) $service['title'], 0, 1)) ?></span><small>Verified provider</small></div>
            <div class="product-body">
              <p class="vendor-name"><a href="<?= $attr($home . '/providers/' . $service['store_slug']) ?>"><?= $esc($service['business_name']) ?></a></p>
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
