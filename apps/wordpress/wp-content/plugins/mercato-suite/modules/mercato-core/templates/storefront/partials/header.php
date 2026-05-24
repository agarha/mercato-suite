<?php
/** @var array $config */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var string|null $theme */
/** @var string|null $current_page */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$theme = (string) ($theme ?? '');
$current = (string) ($current_page ?? '');

if ($theme === 'taskfirst'):
    // Task-First nav: pill-shape, butter-yellow active state, navy "Open app" CTA
?>
<header class="tf-nav" role="banner">
  <a class="tf-brand" href="<?= $attr($home) ?>" aria-label="<?= $attr($config['brand']) ?> home">
    <span class="tf-mark" aria-hidden="true"><?= $esc(\strtolower((string) $config['mark'])) ?></span>
    <span class="tf-brand-name"><?= $esc($config['brand']) ?></span>
  </a>
  <nav class="tf-nav-pills" aria-label="Primary">
    <?php foreach ((array) ($config['nav'] ?? []) as $item):
        if (!\is_array($item)) { continue; }
        $href  = (string) ($item['href']  ?? '#');
        $label = (string) ($item['label'] ?? '');
        // Active when this item's tail matches the current page key.
        $isActive = ($current === 'home' && $href === $home)
            || ($current === 'services'  && \str_ends_with($href, '/services'))
            || ($current === 'providers' && \str_ends_with($href, '/providers'))
            || ($current === 'requests'  && \str_ends_with($href, '/requests/new'))
            || ($current === 'account'   && \str_ends_with($href, '/account'))
            || ($current === 'provider'  && \str_ends_with($href, '/provider/dashboard'));
    ?>
      <a href="<?= $attr($href) ?>"<?= $isActive ? ' class="is-active"' : '' ?>><?= $esc($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="tf-nav-actions">
    <a class="ghost" href="<?= $attr($home . '/signup') ?>">Become a Pro</a>
    <a class="tf-cta-app" href="<?= $attr($home . '/account') ?>">Open app</a>
  </div>
</header>
<?php else: ?>
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
<?php endif; ?>
