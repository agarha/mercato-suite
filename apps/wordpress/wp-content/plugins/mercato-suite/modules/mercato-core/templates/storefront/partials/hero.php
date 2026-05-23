<?php /** @var array $config */ /** @var \Closure $esc */ /** @var \Closure $attr */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii'); ?>
<section class="hero">
  <div>
    <div class="eyebrow">Trusted service marketplace</div>
    <h1><?= $esc($config['hero_headline']) ?></h1>
    <p><?= $esc($config['hero_copy']) ?></p>
    <div class="hero-actions">
      <a class="button" href="<?= $attr($home . '/services') ?>">Explore services</a>
      <a class="button secondary" href="<?= $attr($home . '/providers') ?>">View providers</a>
    </div>
  </div>
  <aside class="hero-media">
    <div class="booking-panel">
      <h3>Find help near you</h3>
      <div class="search-row">
        <div class="field"><span>Service</span><strong>Cleaning, repairs, installs</strong></div>
        <div class="field"><span>Location</span><strong>Toronto area</strong></div>
        <button class="search-btn">Search</button>
      </div>
    </div>
    <div class="photo-board">
      <div class="photo-card photo-a"><span>Home services</span><strong>Verified crews, clear pricing, tracked jobs.</strong></div>
      <div class="photo-card photo-b"><span>Field operations</span><strong>Dispatch, estimates, and provider workflows.</strong></div>
    </div>
  </aside>
</section>
