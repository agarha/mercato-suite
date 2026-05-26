<?php /** @var array $config */ /** @var \Closure $esc */ /** @var \Closure $attr */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii'); ?>
<section class="hero" aria-labelledby="hero-headline">
  <div>
    <div class="eyebrow">Trusted service marketplace</div>
    <h1 id="hero-headline"><?= $esc($config['hero_headline']) ?></h1>
    <p><?= $esc($config['hero_copy']) ?></p>
    <div class="hero-actions">
      <a class="button" href="<?= $attr($home . '/services') ?>">Explore services</a>
      <a class="button secondary" href="<?= $attr($home . '/providers') ?>">View providers</a>
    </div>
  </div>
  <aside class="hero-media" aria-label="Quick search and featured services">
    <div class="booking-panel" role="search" aria-label="Find a service">
      <h3>Find help near you</h3>
      <form class="search-row" action="<?= $attr($home . '/services') ?>" method="get" novalidate>
        <label class="field" for="hero-q">
          <span>Service</span>
          <input id="hero-q" name="q" type="search" placeholder="Cleaning, repairs, installs" autocomplete="off" style="display:block;width:100%;border:0;background:transparent;font:inherit;color:var(--ink);outline:none;margin-top:4px;padding:0">
        </label>
        <label class="field" for="hero-loc">
          <span>Location</span>
          <input id="hero-loc" type="text" placeholder="Toronto area" autocomplete="off" disabled style="display:block;width:100%;border:0;background:transparent;font:inherit;color:var(--ink);outline:none;margin-top:4px;padding:0">
        </label>
        <button class="search-btn" type="submit">Search</button>
      </form>
    </div>
    <div class="photo-board" aria-hidden="true">
      <div class="photo-card photo-a" role="img" aria-label="Home services illustration">
        <span>Home services</span>
        <strong>Verified crews, clear pricing, tracked jobs.</strong>
      </div>
      <div class="photo-card photo-b" role="img" aria-label="Field operations illustration">
        <span>Field operations</span>
        <strong>Dispatch, estimates, and provider workflows.</strong>
      </div>
    </div>
  </aside>
</section>
