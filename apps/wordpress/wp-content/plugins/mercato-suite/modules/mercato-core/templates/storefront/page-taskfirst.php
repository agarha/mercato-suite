<?php
/**
 * Task-First home page template.
 *
 * Used when the tenant's storefront config sets `theme: "taskfirst"`.
 * Bespoke layout per the Gigsii design canvas (winning direction).
 * NOT the default Mercato page — that remains page.php.
 *
 * @var array<string,mixed> $config
 * @var array<string,mixed> $data
 * @var string $asset_url
 * @var string $partials
 * @var \Closure $esc
 * @var \Closure $attr
 * @var \Closure $money
 */

$home          = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$brand_initial = (string) ($config['mark'] ?? 'g');
$brand         = (string) ($config['brand'] ?? 'Gigsii');
$tf            = (array) ($config['taskfirst'] ?? []);

$status_chip   = (string) ($tf['status_chip']   ?? '1,248 pros online · avg. 14 min response');
$hero_lead     = (string) ($tf['hero_lead']     ?? 'What');
$hero_accent   = (string) ($tf['hero_accent']   ?? 'needs doing');
$hero_trail    = (string) ($tf['hero_trail']    ?? 'today?');
$hero_copy     = (string) ($config['hero_copy'] ?? 'Tell us what is broken — or what you would love off your list. Local pros will reach out with a quote in minutes.');
$input_label   = (string) ($tf['input_label']   ?? 'Describe it however you want');
$input_text    = (string) ($tf['input_text']    ?? '"My kitchen sink is leaking under the cabinet…"');
$primary_cta   = (string) ($config['primary_cta'] ?? 'Find me help');
$polaroid_label= (string) ($tf['polaroid_label']?? 'Worker in action');
$polaroid_cap  = (string) ($tf['polaroid_caption'] ?? "today's fix ✓");
$sticker_top   = (string) ($tf['sticker_top']   ?? 'smiled');
$sticker_btm   = (string) ($tf['sticker_bottom']?? 'at 4:18pm');

$chips = (array) ($tf['chips'] ?? [
    ['icon' => '🔧', 'label' => 'Leaky faucet'],
    ['icon' => '🧹', 'label' => 'Deep clean (3BR)'],
    ['icon' => '📺', 'label' => 'Mount a TV'],
    ['icon' => '⚡', 'label' => 'Outlet not working'],
    ['icon' => '❄️', 'label' => "AC won't cool"],
    ['icon' => '🚪', 'label' => 'Squeaky door'],
    ['icon' => '🏠', 'label' => 'Move-out clean'],
]);

$how_eyebrow = (string) ($tf['how_eyebrow'] ?? 'How it goes');
$how_h2_lead = (string) ($tf['how_h2_lead'] ?? 'From "ugh" to');
$how_h2_em   = (string) ($tf['how_h2_em']   ?? 'fixed');
$how_h2_tail = (string) ($tf['how_h2_tail'] ?? ', in three messages.');

$how_steps = (array) ($tf['how_steps'] ?? [
    ['blob' => 'peach',  'icon' => '✍️', 'title' => 'Tell us in your words',     'body' => 'Photos help. We use them to match you with pros who actually handle that kind of work.'],
    ['blob' => 'sage',   'icon' => '💬', 'title' => 'Pros reach out',            'body' => 'Three nearby pros respond with a quote, ETA and parts list. No lead fees on their end — they charge what the job is worth.'],
    ['blob' => 'butter', 'icon' => '🤝', 'title' => 'You pick. We hold deposit.','body' => "Pay when you sign off on the work. If something is wrong, we sort it."],
]);

$pros_eyebrow = (string) ($tf['pros_eyebrow'] ?? 'People, not algorithms');
$pros_h2_lead = (string) ($tf['pros_h2_lead'] ?? 'Meet a few of the');
$pros_h2_em   = (string) ($tf['pros_h2_em']   ?? '1,248');
$pros_h2_tail = (string) ($tf['pros_h2_tail'] ?? 'pros nearby.');
$pros_link    = (string) ($tf['pros_link']    ?? 'Browse the directory →');

$provider_cta_eyebrow = (string) ($tf['provider_cta_eyebrow'] ?? 'For tradespeople');
$provider_cta_lead    = (string) ($tf['provider_cta_lead']    ?? 'Your phone, full of');
$provider_cta_em      = (string) ($tf['provider_cta_em']      ?? 'real jobs');
$provider_cta_tail    = (string) ($tf['provider_cta_tail']    ?? '.');
$provider_cta_copy    = (string) ($tf['provider_cta_copy']    ?? 'Flat 4% on what you earn. No leads. No subscription.');
$provider_cta_button  = (string) ($tf['provider_cta_button']  ?? 'List your trade →');

/*
 * Pull a few provider cards out of the snapshot. Repository::snapshot()
 * surfaces a `vendors` list keyed by approved provider rows. We fall back
 * to a pastel set if nothing is seeded so the demo never looks empty.
 */
$snapshot      = (array) ($data ?? []);
$vendors_raw   = (array) ($snapshot['vendors'] ?? []);
$photo_classes = ['', 'peach', 'butter', 'sky'];

$provider_cards = [];
$i = 0;
foreach ($vendors_raw as $vendor) {
    if (!\is_array($vendor)) {
        continue;
    }
    $provider_cards[] = [
        'name'   => (string) ($vendor['business_name'] ?? $vendor['store_slug'] ?? 'Local pro'),
        'trade'  => (string) ($vendor['primary_category_name'] ?? $vendor['category'] ?? 'Local services'),
        'rating' => isset($vendor['avg_rating']) ? \number_format((float) $vendor['avg_rating'], 2) : '4.9',
        'jobs'   => (int) ($vendor['jobs_count'] ?? $vendor['reviews_count'] ?? 0),
        'photo'  => $photo_classes[$i % 4],
        'href'   => $home . '/providers/' . (string) ($vendor['store_slug'] ?? ''),
    ];
    $i++;
    if (\count($provider_cards) >= 4) {
        break;
    }
}
if ($provider_cards === []) {
    $provider_cards = [
        ['name' => 'Maple Goh',   'trade' => 'Plumbing · Oakland',   'rating' => '4.93', 'jobs' => 612,  'photo' => '',       'href' => $home . '/providers'],
        ['name' => 'Brigid Lin',  'trade' => 'Cleaning · SF',        'rating' => '4.88', 'jobs' => 1240, 'photo' => 'peach',  'href' => $home . '/providers'],
        ['name' => 'Andre Vance', 'trade' => 'Electrical · Berkeley','rating' => '4.96', 'jobs' => 408,  'photo' => 'butter', 'href' => $home . '/providers'],
        ['name' => 'Kai Park',    'trade' => 'Handyman · Alameda',   'rating' => '4.81', 'jobs' => 873,  'photo' => 'sky',    'href' => $home . '/providers'],
    ];
}

$nav = (array) ($config['nav'] ?? []);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $esc($config['title'] ?? $brand) ?></title>
  <meta name="description" content="<?= $attr($config['hero_copy'] ?? '') ?>">
  <meta property="og:title" content="<?= $attr($config['title'] ?? $brand) ?>">
  <meta property="og:description" content="<?= $attr($config['hero_copy'] ?? '') ?>">
  <meta property="og:type" content="website">
  <meta name="theme-color" content="#1f2a52">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css') ?>">
</head>
<body class="dir-taskfirst">
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="tf-nav" role="banner">
    <a class="tf-brand" href="<?= $attr($home) ?>" aria-label="<?= $attr($brand) ?> home">
      <span class="tf-mark" aria-hidden="true"><?= $esc(\strtolower($brand_initial)) ?></span>
      <span class="tf-brand-name"><?= $esc($brand) ?></span>
    </a>
    <nav class="tf-nav-pills" aria-label="Primary">
      <?php foreach ($nav as $item):
          if (!\is_array($item)) { continue; }
          $href  = (string) ($item['href']  ?? '#');
          $label = (string) ($item['label'] ?? '');
          $is_home_link = ($href === $home);
      ?>
        <a href="<?= $attr($href) ?>"<?= $is_home_link ? ' class="is-active"' : '' ?>><?= $esc($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="tf-nav-actions">
      <button class="ghost" type="button">Sign in</button>
      <a class="tf-cta-app" href="<?= $attr($home . '/account') ?>">Open app</a>
    </div>
  </header>

  <main id="main" tabindex="-1">
    <section class="tf-hero" aria-labelledby="tf-hero-headline">
      <div class="tf-polaroid" aria-hidden="true">
        <div class="tf-polaroid-tape"></div>
        <div class="tf-polaroid-frame" data-label="<?= $attr($polaroid_label) ?>"></div>
        <div class="tf-polaroid-caption"><?= $esc($polaroid_cap) ?></div>
      </div>
      <div class="tf-sticker" aria-hidden="true">
        <?= $esc($sticker_top) ?><br>
        <small><?= $esc($sticker_btm) ?></small>
      </div>

      <div class="tf-hero-inner">
        <div class="tf-status-chip">
          <span class="tf-status-dot" aria-hidden="true"></span>
          <?= $esc($status_chip) ?>
        </div>
        <h1 id="tf-hero-headline" class="tf-hero-headline">
          <?= $esc($hero_lead) ?><br>
          <em><?= $esc($hero_accent) ?></em> <?= $esc($hero_trail) ?>
        </h1>
        <p class="tf-hero-copy"><?= $esc($hero_copy) ?></p>

        <form class="tf-input-card" method="get" action="<?= $attr($home . '/services') ?>" novalidate>
          <div class="tf-input-body">
            <div class="tf-input-label"><?= $esc($input_label) ?></div>
            <label class="sr-only" for="tf-q">Describe what you need help with</label>
            <input class="tf-input-text" id="tf-q" name="q" type="search" placeholder="<?= $attr($input_text) ?>" autocomplete="off">
          </div>
          <button class="tf-input-submit" type="submit">
            <?= $esc($primary_cta) ?>
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <div class="tf-chips" role="list" aria-label="Common requests">
          <?php foreach ($chips as $chip):
              if (!\is_array($chip)) { continue; }
              $icon  = (string) ($chip['icon']  ?? '');
              $label = (string) ($chip['label'] ?? '');
              $href  = $home . '/services?q=' . \rawurlencode($label);
          ?>
            <a class="tf-chip" role="listitem" href="<?= $attr($href) ?>">
              <span class="tf-chip-icon" aria-hidden="true"><?= $esc($icon) ?></span>
              <?= $esc($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="tf-how" aria-labelledby="tf-how-headline">
      <div class="tf-how-inner">
        <p class="tf-eyebrow"><?= $esc($how_eyebrow) ?></p>
        <h2 id="tf-how-headline" class="tf-h2">
          <?= $esc($how_h2_lead) ?> <em><?= $esc($how_h2_em) ?></em><?= $esc($how_h2_tail) ?>
        </h2>
        <div class="tf-how-grid">
          <?php $step_i = 0; foreach ($how_steps as $step):
              if (!\is_array($step)) { continue; }
              $blob  = (string) ($step['blob']  ?? 'peach');
              $icon  = (string) ($step['icon']  ?? '✓');
              $title = (string) ($step['title'] ?? '');
              $body  = (string) ($step['body']  ?? '');
              $step_i++;
          ?>
            <div class="tf-how-card">
              <div class="tf-how-blob <?= $attr($blob) ?>" aria-hidden="true"></div>
              <div class="tf-how-row">
                <span class="tf-how-icon" aria-hidden="true"><?= $esc($icon) ?></span>
                <span class="tf-step-tag">step <?= $esc((string) $step_i) ?>/<?= $esc((string) \count($how_steps)) ?></span>
              </div>
              <h3><?= $esc($title) ?></h3>
              <p><?= $esc($body) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="tf-pros" aria-labelledby="tf-pros-headline">
      <div class="tf-pros-head">
        <div>
          <p class="tf-pros-eyebrow"><?= $esc($pros_eyebrow) ?></p>
          <h2 id="tf-pros-headline" class="tf-pros-h2">
            <?= $esc($pros_h2_lead) ?> <em><?= $esc($pros_h2_em) ?></em> <?= $esc($pros_h2_tail) ?>
          </h2>
        </div>
        <a class="tf-pros-cta-link" href="<?= $attr($home . '/providers') ?>"><?= $esc($pros_link) ?></a>
      </div>
      <div class="tf-pros-grid">
        <?php foreach ($provider_cards as $p): ?>
          <a class="tf-pro-card" href="<?= $attr($p['href']) ?>">
            <span class="tf-pro-photo <?= $attr((string) $p['photo']) ?>" aria-hidden="true"></span>
            <div class="tf-pro-body">
              <div class="tf-pro-name"><?= $esc($p['name']) ?></div>
              <div class="tf-pro-trade"><?= $esc($p['trade']) ?></div>
              <div class="tf-pro-row">
                <span class="tf-pro-rating">★ <?= $esc($p['rating']) ?></span>
                <span class="tf-pro-jobs"><?= $esc((string) $p['jobs']) ?> jobs</span>
                <span class="tf-pro-status">● Available</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="tf-provider-cta" aria-labelledby="tf-prov-cta-headline">
      <div class="tf-provider-cta-card">
        <div class="tf-provider-cta-blob" aria-hidden="true"></div>
        <div>
          <p class="tf-provider-cta-eyebrow"><?= $esc($provider_cta_eyebrow) ?></p>
          <h2 id="tf-prov-cta-headline" class="tf-provider-cta-h2">
            <?= $esc($provider_cta_lead) ?> <em><?= $esc($provider_cta_em) ?></em><?= $esc($provider_cta_tail) ?>
          </h2>
          <p class="tf-provider-cta-copy"><?= $esc($provider_cta_copy) ?></p>
        </div>
        <a class="tf-provider-cta-button" href="<?= $attr($home . '/provider/dashboard') ?>"><?= $esc($provider_cta_button) ?></a>
      </div>
    </section>
  </main>

  <footer class="tf-footer" role="contentinfo">
    <span><?= $esc($config['footer'] ?? '') ?></span>
    <span class="tf-footer-links">
      <a href="<?= $attr($home . '/services') ?>">Services</a>
      <a href="<?= $attr($home . '/providers') ?>">Providers</a>
      <a href="<?= $attr($home . '/requests/new') ?>">Post a request</a>
      <a href="<?= $attr($home . '/account') ?>">My account</a>
    </span>
  </footer>
</body>
</html>
