<?php
/** @var array $config */
/** @var \Closure $esc */
/** @var \Closure $attr */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
?>
<header class="topbar" role="banner">
  <a class="brand" href="<?= $attr($home) ?>" aria-label="<?= $attr($config['brand']) ?> home">
    <span class="mark" aria-hidden="true"><?= $esc($config['mark']) ?></span>
    <span><?= $esc($config['brand']) ?></span>
  </a>
  <nav class="nav" aria-label="Primary">
    <?php foreach ((array) ($config['nav'] ?? []) as $item): if (!is_array($item)) continue; ?>
      <a href="<?= $attr($item['href'] ?? '#') ?>"><?= $esc($item['label'] ?? '') ?></a>
    <?php endforeach; ?>
  </nav>
</header>
