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
$search_near = (string) ($search_near ?? '');
$search_near_display = (string) ($search_near_display ?? '');
$search_radius_km = (float) ($search_radius_km ?? 25);
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$resultCount = count($data['services']);
$hasFilter = ($search_q !== '' || $search_category > 0 || $search_near !== '');
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
  <?php if (($theme ?? '') === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css') ?>"><?php endif; ?>
</head>
<body<?= ($theme ?? '') === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
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
            <form class="search-row geo-search" action="<?= $attr($home . '/services') ?>" method="get" novalidate>
              <label class="field" for="svc-q">
                <span>Keywords</span>
                <input id="svc-q" name="q" type="search" value="<?= $attr($search_q) ?>" placeholder="cleaning, repairs, install…" autocomplete="off">
              </label>
              <label class="field" for="svc-cat">
                <span>Category</span>
                <select id="svc-cat" name="category">
                  <option value="0">Any category</option>
                  <?php foreach ($data['categories'] as $category): ?>
                    <option value="<?= $attr($category['category_id']) ?>"<?= $search_category === (int) $category['category_id'] ? ' selected' : '' ?>><?= $esc($category['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field" for="svc-near">
                <span>Near</span>
                <input id="svc-near" name="near" type="text" value="<?= $attr($search_near) ?>" placeholder="postcode or suburb" autocomplete="postal-code">
              </label>
              <label class="field" for="svc-radius">
                <span>Within</span>
                <select id="svc-radius" name="radius">
                  <?php foreach ([5, 10, 25, 50, 100] as $r): ?>
                    <option value="<?= $r ?>"<?= (int) $search_radius_km === $r ? ' selected' : '' ?>><?= $r ?> km</option>
                  <?php endforeach; ?>
                </select>
              </label>
              <input type="hidden" name="lat" id="svc-lat">
              <input type="hidden" name="lng" id="svc-lng">
              <div class="search-actions">
                <button type="button" class="button secondary geo-locate-btn" data-geo-btn>
                  <span aria-hidden="true">📍</span> Use my location
                </button>
                <button type="submit" class="search-btn">Search</button>
              </div>
            </form>
            <?php if ($search_near_display !== '' && $search_near_display !== $search_near): ?>
              <p class="geo-resolved-hint" aria-live="polite">Showing pros near <strong><?= $esc($search_near_display) ?></strong> within <?= (int) $search_radius_km ?> km.</p>
            <?php endif; ?>
            <?php if ($hasFilter): ?>
              <p class="clear-filters">
                <a href="<?= $attr($home . '/services') ?>">Clear filters</a>
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
          $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4];
          $pricingType = (string) ($service['pricing_type'] ?? 'fixed');
          $unitLabel = (string) ($service['unit_label'] ?? '');
          $pricingSuffix = match ($pricingType) {
            'hourly' => ' / hr',
            'per_unit' => $unitLabel !== '' ? ' / ' . $unitLabel : ' / unit',
            'quote_required' => '',
            default => '',
          };
          $distance = $service['distance_km'] ?? null;
          $servesArea = !empty($service['serves_area']);
        ?>
          <article class="product-card">
            <div class="product-media <?= $tone ?>">
              <?php if (!empty($service['photo_url'])): ?>
                <img src="<?= $attr($service['photo_url']) ?>" alt="">
              <?php else: ?>
                <span><?= $esc(mb_substr((string) $service['title'], 0, 1)) ?></span>
              <?php endif; ?>
              <?php if ($servesArea): ?><small class="badge-serves">✓ Serves your area</small>
              <?php elseif ($distance !== null): ?><small class="badge-distance"><?= (string) $distance ?> km away</small>
              <?php else: ?><small>Verified provider</small><?php endif; ?>
            </div>
            <div class="product-body">
              <p class="vendor-name"><a href="<?= $attr($home . '/providers/' . $service['store_slug']) ?>"><?= $esc($service['business_name']) ?></a><?php if (!empty($service['years_experience'])): ?> · <span class="exp-pill"><?= (int) $service['years_experience'] ?>+ yrs</span><?php endif; ?></p>
              <h3><?= $esc($service['title']) ?></h3>
              <?php if (!empty($service['headline'])): ?><p class="headline-line"><?= $esc($service['headline']) ?></p><?php endif; ?>
              <p><?= $esc($service['summary'] ?: ($service['description'] ?: $config['item_fallback_copy'])) ?></p>
              <div class="product-meta">
                <?php if ($pricingType === 'quote_required'): ?>
                  <strong>Quote on request</strong>
                <?php else: ?>
                  <strong><?= $money($service['price_minor']) ?><?= $pricingSuffix !== '' ? '<span class="price-suffix">' . $esc($pricingSuffix) . '</span>' : '' ?></strong>
                <?php endif; ?>
                <?php if (!empty($service['duration_minutes'])): ?>
                  <span>~<?= (int) $service['duration_minutes'] ?> min</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
<script>
// Wire the "Use my location" button on the geo-search form. On success
// fills the hidden lat/lng inputs and submits — the server uses the exact
// coords and skips Nominatim. Failure leaves the typed postcode/suburb to
// be geocoded server-side.
(function () {
  var btn = document.querySelector('[data-geo-btn]');
  if (!btn || !navigator.geolocation) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Locating…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = document.getElementById('svc-lat') || document.getElementById('pro-lat');
      var lng = document.getElementById('svc-lng') || document.getElementById('pro-lng');
      if (lat) lat.value = pos.coords.latitude.toFixed(7);
      if (lng) lng.value = pos.coords.longitude.toFixed(7);
      var form = btn.closest('form');
      if (form) form.submit();
    }, function () {
      btn.disabled = false;
      btn.textContent = '📍 Use my location';
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 });
  });
})();
</script>
</body>
</html>
