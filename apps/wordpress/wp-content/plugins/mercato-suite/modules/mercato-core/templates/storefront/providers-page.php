<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$theme = (string) ($theme ?? '');
$search_category = (int) ($search_category ?? 0);
$search_near = (string) ($search_near ?? '');
$search_near_display = (string) ($search_near_display ?? '');
$search_radius_km = (float) ($search_radius_km ?? 25);
$hasFilter = ($search_category > 0 || $search_near !== '');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Providers - <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Verified, insured local pros on <?= $attr($config['brand']) ?>.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css') ?>">
  <?php if ($theme === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css') ?>"><?php endif; ?>
</head>
<body<?= $theme === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero" aria-labelledby="pro-heading">
        <div>
          <div class="eyebrow">Provider directory</div>
          <h1 id="pro-heading"><?= $hasFilter ? 'Pros near you' : 'Local pros you can trust' ?></h1>
          <p>Every pro is identity-verified and payouts-ready. Filter by area to find someone who already serves your suburb.</p>
        </div>
        <aside class="hero-media" aria-label="Find a pro">
          <div class="booking-panel" role="search">
            <h3>Find a pro</h3>
            <form class="search-row geo-search providers-search" action="<?= $attr($home . '/providers') ?>" method="get" novalidate>
              <label class="field" for="pro-near">
                <span>Near</span>
                <input id="pro-near" name="near" type="text" value="<?= $attr($search_near) ?>" placeholder="postcode or suburb" autocomplete="postal-code">
              </label>
              <label class="field" for="pro-radius">
                <span>Within</span>
                <select id="pro-radius" name="radius">
                  <?php foreach ([5, 10, 25, 50, 100] as $r): ?>
                    <option value="<?= $r ?>"<?= (int) $search_radius_km === $r ? ' selected' : '' ?>><?= $r ?> km</option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field" for="pro-cat">
                <span>Category</span>
                <select id="pro-cat" name="category">
                  <option value="0">Any category</option>
                  <?php foreach ($data['categories'] as $category): ?>
                    <option value="<?= $attr($category['category_id']) ?>"<?= $search_category === (int) $category['category_id'] ? ' selected' : '' ?>><?= $esc($category['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <input type="hidden" name="lat" id="pro-lat">
              <input type="hidden" name="lng" id="pro-lng">
              <div class="search-actions">
                <button type="button" class="button secondary geo-locate-btn" data-geo-btn>Use my location</button>
                <button type="submit" class="search-btn">Search</button>
              </div>
            </form>
            <?php if ($search_near_display !== '' && $search_near_display !== $search_near): ?>
              <p class="geo-resolved-hint">Showing pros near <strong><?= $esc($search_near_display) ?></strong>.</p>
            <?php endif; ?>
            <?php if ($hasFilter): ?>
              <p class="clear-filters"><a href="<?= $attr($home . '/providers') ?>">Clear filters</a></p>
            <?php endif; ?>
          </div>
        </aside>
      </section>
    </div>

    <section class="section">
      <div class="section-head">
        <div>
          <h2><?= count($data['providers']) ?> <?= $hasFilter ? 'matching pros' : 'verified pros' ?></h2>
          <p>Sorted by proximity when location filter is active.</p>
        </div>
        <span class="pill">KYC + Stripe Connect</span>
      </div>

      <div class="vendor-grid">
        <?php if (empty($data['providers'])): ?>
          <article class="empty-state">
            <h3>No matches in that area</h3>
            <p>Try widening the radius or <a href="<?= $attr($home . '/providers') ?>">clear filters</a> to see everyone.</p>
          </article>
        <?php else: foreach ($data['providers'] as $provider):
          $rating = round((float) ($provider['avg_rating'] ?? 0), 1);
          $reviews = (int) ($provider['review_count'] ?? 0);
          $verified = !empty($provider['verified_at']);
          $bgCheckPassed = (string) ($provider['background_check_status'] ?? '') === 'passed';
          $distance = $provider['distance_km'] ?? null;
          $servesArea = !empty($provider['serves_area']);
          $hourly = $provider['hourly_rate_minor'] ? '$' . number_format($provider['hourly_rate_minor'] / 100, 0) . '/hr' : null;
        ?>
          <article class="vendor-card pro-card">
            <div class="vendor-avatar">
              <?php if (!empty($provider['photo_url'])): ?>
                <img src="<?= $attr($provider['photo_url']) ?>" alt="" loading="lazy">
              <?php else: ?>
                <?= $esc(mb_substr((string) $provider['business_name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div class="pro-card-body">
              <h3>
                <a href="<?= $attr($home . '/providers/' . $provider['store_slug']) ?>"><?= $esc($provider['business_name']) ?></a>
                <?php if ($verified): ?><span class="badge-verified" title="Identity verified">&check;</span><?php endif; ?>
              </h3>
              <?php if (!empty($provider['headline'])): ?>
                <p class="pro-headline"><?= $esc($provider['headline']) ?></p>
              <?php endif; ?>
              <ul class="pro-meta">
                <?php if ($reviews > 0): ?>
                  <li class="rating">&starf; <?= number_format($rating, 1) ?> <small>(<?= $reviews ?>)</small></li>
                <?php endif; ?>
                <?php if (!empty($provider['years_experience'])): ?>
                  <li><?= (int) $provider['years_experience'] ?>+ yrs exp</li>
                <?php endif; ?>
                <li><?= (int) $provider['service_count'] ?> services</li>
                <?php if ($hourly): ?><li class="from-rate">From <?= $esc($hourly) ?></li><?php endif; ?>
              </ul>
              <ul class="pro-badges">
                <?php if ($bgCheckPassed): ?><li>Background-checked</li><?php endif; ?>
                <?php if (!empty($provider['license_number'])): ?><li>Licensed</li><?php endif; ?>
                <?php if (!empty($provider['insurance_amount_minor'])): ?><li>Insured $<?= number_format($provider['insurance_amount_minor'] / 100, 0) ?></li><?php endif; ?>
                <?php if ($servesArea): ?><li class="badge-serves-area">Serves your area</li>
                <?php elseif ($distance !== null): ?><li class="badge-distance"><?= (string) $distance ?> km away</li><?php endif; ?>
              </ul>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
<script>
(function () {
  var btn = document.querySelector('[data-geo-btn]');
  if (!btn || !navigator.geolocation) return;
  btn.addEventListener('click', function () {
    btn.disabled = true; btn.textContent = 'Locating...';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = document.getElementById('pro-lat');
      var lng = document.getElementById('pro-lng');
      if (lat) lat.value = pos.coords.latitude.toFixed(7);
      if (lng) lng.value = pos.coords.longitude.toFixed(7);
      btn.closest('form').submit();
    }, function () {
      btn.disabled = false; btn.textContent = 'Use my location';
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 });
  });
})();
</script>
</body>
</html>
