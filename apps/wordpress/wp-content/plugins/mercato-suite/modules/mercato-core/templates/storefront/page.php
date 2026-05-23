<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $esc($config['title']) ?></title>
  <meta name="description" content="<?= $attr($config['hero_copy']) ?>">
  <meta property="og:title" content="<?= $attr($config['title']) ?>">
  <meta property="og:description" content="<?= $attr($config['hero_copy']) ?>">
  <meta property="og:type" content="website">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css') ?>">
</head>
<body>
  <?php include $partials . '/header.php'; ?>
  <main>
    <div class="hero-wrap">
      <?php include $partials . '/hero.php'; ?>
      <?php include $partials . '/metrics.php'; ?>
    </div>
    <?php include $partials . '/positioning.php'; ?>
    <?php include $partials . '/categories.php'; ?>
    <?php include $partials . '/services.php'; ?>
    <?php include $partials . '/vendors.php'; ?>
    <?php include $partials . '/buyer.php'; ?>
    <?php include $partials . '/requests.php'; ?>
    <?php include $partials . '/features.php'; ?>
    <?php include $partials . '/operations.php'; ?>
    <?php include $partials . '/seller.php'; ?>
    <?php include $partials . '/workflow.php'; ?>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
