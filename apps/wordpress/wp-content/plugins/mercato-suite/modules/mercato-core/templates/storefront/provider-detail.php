<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
$provider = $data['provider'];
$theme = (string) ($theme ?? '');
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$avg = (float) ($data['review_average'] ?? 0);
$count = (int) ($data['review_count'] ?? 0);
$reviews = (array) ($data['reviews'] ?? []);
$serviceAreas = (array) ($data['service_areas'] ?? []);
$locations = (array) ($data['locations'] ?? []);
$portfolio = (array) ($data['portfolio'] ?? []);
$verified = !empty($provider['verified_at']);
$bgPassed = (string) ($provider['background_check_status'] ?? '') === 'passed';
$hourly = !empty($provider['hourly_rate_minor']) ? '$' . number_format($provider['hourly_rate_minor'] / 100, 0) : null;
$stars = static function (float $rating): string {
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('★', $full) . str_repeat('⯨', $half) . str_repeat('☆', $empty);
};
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $esc($provider['business_name']) ?> — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="<?= $attr($provider['headline'] ?: $provider['business_name']) ?> on <?= $attr($config['brand']) ?>.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront.css')) ?>">
  <?php if ($theme === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront-taskfirst.css')) ?>"><?php endif; ?>
</head>
<body<?= $theme === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero provider-hero" aria-labelledby="pro-title">
        <div>
          <div class="provider-header">
            <div class="provider-avatar-large">
              <?php if (!empty($provider['photo_url'])): ?>
                <img src="<?= $attr($provider['photo_url']) ?>" alt="">
              <?php else: ?>
                <?= $esc(mb_substr((string) $provider['business_name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div>
              <div class="eyebrow"><?php if ($verified): ?>✓ Verified provider<?php else: ?>Provider profile<?php endif; ?></div>
              <h1 id="pro-title"><?= $esc($provider['business_name']) ?></h1>
              <?php if (!empty($provider['headline'])): ?>
                <p class="pro-tagline"><?= $esc($provider['headline']) ?></p>
              <?php endif; ?>
              <ul class="pro-meta inline">
                <?php if ($count > 0): ?>
                  <li class="rating"><?= $stars($avg) ?> <strong><?= number_format($avg, 1) ?></strong> <small>(<?= $count ?>)</small></li>
                <?php endif; ?>
                <?php if (!empty($provider['years_experience'])): ?><li><?= (int) $provider['years_experience'] ?>+ yrs exp</li><?php endif; ?>
                <?php if (!empty($provider['languages'])): ?><li>🗣 <?= $esc($provider['languages']) ?></li><?php endif; ?>
                <?php if ($hourly): ?><li class="from-rate">From <strong><?= $esc($hourly) ?>/hr</strong></li><?php endif; ?>
              </ul>
            </div>
          </div>

          <?php if (!empty($provider['bio'])): ?>
            <div class="pro-bio">
              <h3>About</h3>
              <p><?= nl2br($esc($provider['bio'])) ?></p>
            </div>
          <?php endif; ?>

          <div class="hero-actions">
            <a class="button" href="<?= $attr($home . '/requests/new?provider=' . $provider['store_slug']) ?>">Request a quote</a>
            <a class="button secondary" href="<?= $attr($home . '/providers') ?>">Back to all providers</a>
          </div>
        </div>

        <aside class="hero-media">
          <div class="booking-panel pro-trust-panel">
            <h3>Trust &amp; safety</h3>
            <ul class="trust-list">
              <li><?= $verified ? '✓ Identity verified' : '✗ Identity not verified' ?></li>
              <li><?= $bgPassed ? '✓ Background-checked' : '— Background check ' . $esc($provider['background_check_status'] ?? 'not_started') ?></li>
              <li><?= !empty($provider['license_number']) ? '✓ Licensed #' . $esc($provider['license_number']) : '— No license on file' ?></li>
              <li><?= !empty($provider['insurance_amount_minor']) ? '✓ Insured to $' . number_format($provider['insurance_amount_minor'] / 100, 0) : '— No insurance on file' ?></li>
              <li><?= !empty($provider['stripe_account_id']) ? '✓ Stripe-connected payouts' : '— Stripe payouts pending' ?></li>
            </ul>
          </div>
        </aside>
      </section>
    </div>

    <!-- Services catalog (multi-service Fiverr/Upwork style) -->
    <section class="section">
      <div class="section-head">
        <div><h2>What <?= $esc($provider['business_name']) ?> offers</h2><p><?= count($data['services']) ?> service<?= count($data['services']) === 1 ? '' : 's' ?> available right now.</p></div>
      </div>
      <div class="product-grid">
        <?php if (empty($data['services'])): ?>
          <article class="empty-state"><h3>No services published yet</h3><p>This pro hasn't published bookable services.</p></article>
        <?php else: foreach ($data['services'] as $index => $service):
          $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4];
          $pricingType = (string) ($service['pricing_type'] ?? 'fixed');
          $unitLabel = (string) ($service['unit_label'] ?? '');
          $pricingSuffix = match ($pricingType) {
            'hourly', 'per_hour' => ' / hr',
            'per_day' => ' / day',
            'per_week' => ' / week',
            'per_month' => ' / month',
            'per_unit' => $unitLabel !== '' ? ' / ' . $unitLabel : ' / unit',
            'quote_required' => '',
            default => '',
          };
          $listingType = (string) ($service['listing_type'] ?? 'service');
          $listingBadge = match ($listingType) {
            'rental' => ['label' => 'Rental', 'class' => 'badge-rental'],
            'digital' => ['label' => 'Digital', 'class' => 'badge-digital'],
            'physical' => ['label' => 'Item', 'class' => 'badge-physical'],
            default => null,
          };
        ?>
          <article class="product-card">
            <div class="product-media <?= $tone ?>">
              <span><?= $esc(mb_substr((string) $service['title'], 0, 1)) ?></span>
              <small><?= $esc(ucfirst(str_replace('_', ' ', $pricingType))) ?></small>
            </div>
            <div class="product-body">
              <h3>
                <?= $esc($service['title']) ?>
                <?php if ($listingBadge !== null): ?>
                  <span class="listing-type-badge <?= $attr($listingBadge['class']) ?>"><?= $esc($listingBadge['label']) ?></span>
                <?php endif; ?>
              </h3>
              <p><?= $esc($service['summary'] ?: ($service['description'] ?: $config['item_fallback_copy'])) ?></p>
              <div class="product-meta">
                <?php if ($pricingType === 'quote_required'): ?>
                  <strong>Quote on request</strong>
                <?php elseif ((int) $service['price_minor'] === 0): ?>
                  <strong>Free consultation</strong>
                <?php else: ?>
                  <strong><?= $money($service['price_minor']) ?><?php if ($pricingSuffix !== ''): ?><span class="price-suffix"><?= $esc($pricingSuffix) ?></span><?php endif; ?></strong>
                <?php endif; ?>
                <?php if (!empty($service['duration_minutes'])): ?>
                  <span>~<?= (int) $service['duration_minutes'] ?> min</span>
                <?php endif; ?>
              </div>
              <a class="button" href="<?= $attr($home . '/requests/new?provider=' . $provider['store_slug'] . '&service=' . (int) $service['product_id']) ?>">Book this</a>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <?php if (!empty($serviceAreas) || !empty($locations)): ?>
    <section class="section" id="service-areas">
      <div class="section-head">
        <div><h2>Where this pro works</h2></div>
      </div>
      <div class="account-panel">
        <?php if (!empty($locations)): ?>
          <p class="area-label">Based in</p>
          <ul class="area-chips">
            <?php foreach ($locations as $loc): ?>
              <li>
                <strong><?= $esc($loc['label'] ?: 'Main location') ?></strong>
                <?= $esc(trim(($loc['city'] ?? '') . ' ' . ($loc['region'] ?? ''))) ?>
                <?php if (!empty($loc['service_radius_km'])): ?>
                  &middot; <?= (int) $loc['service_radius_km'] ?> km radius
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (!empty($serviceAreas)): ?>
          <p class="area-label">Also covers</p>
          <ul class="area-chips">
            <?php foreach ($serviceAreas as $area): ?>
              <li><strong><?= $esc($area['label']) ?></strong><?php if (!empty($area['radius_km'])): ?> &middot; <?= (int) $area['radius_km'] ?> km<?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($portfolio)): ?>
    <section class="section">
      <div class="section-head"><div><h2>Past work</h2></div></div>
      <div class="portfolio-grid">
        <?php foreach ($portfolio as $p): ?>
          <figure class="portfolio-item">
            <img src="<?= $attr($p['photo_url']) ?>" alt="<?= $attr($p['caption'] ?: '') ?>">
            <?php if (!empty($p['caption'])): ?><figcaption><?= $esc($p['caption']) ?></figcaption><?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="section" id="reviews">
      <div class="section-head">
        <div><h2>Reviews</h2><p>Verified buyer ratings and comments for this provider.</p></div>
        <?php if ($count > 0): ?>
          <span class="pill"><?= $stars($avg) ?> <?= number_format($avg, 1) ?>/5 &middot; <?= $count ?> total</span>
        <?php endif; ?>
      </div>

      <?php if (empty($reviews)): ?>
        <article class="empty-state">
          <h3>No reviews yet</h3>
          <p>Be the first to share your experience after a complet