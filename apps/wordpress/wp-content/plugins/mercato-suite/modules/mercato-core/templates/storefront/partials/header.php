<?php /** @var array $config */ /** @var \Closure $esc */ /** @var \Closure $attr */ ?>
<header class="topbar">
  <div class="brand"><div class="mark"><?= $esc($config['mark']) ?></div><?= $esc($config['brand']) ?></div>
  <nav class="nav">
    <?php foreach ((array) ($config['nav'] ?? []) as $item): if (!is_array($item)) continue; ?>
      <a href="<?= $attr($item['href'] ?? '#') ?>"><?= $esc($item['label'] ?? '') ?></a>
    <?php endforeach; ?>
  </nav>
</header>
