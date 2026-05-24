<?php
/** @var array $config */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var string|null $theme */
$theme = (string) ($theme ?? '');
$home  = '/t/' . ($config['tenant_slug'] ?? 'gigsii');

if ($theme === 'taskfirst'):
?>
<footer class="tf-footer" role="contentinfo">
  <span><?= $esc($config['footer']) ?></span>
  <span class="tf-footer-links">
    <a href="<?= $attr($home . '/services') ?>">Services</a>
    <a href="<?= $attr($home . '/providers') ?>">Providers</a>
    <a href="<?= $attr($home . '/requests/new') ?>">Post a request</a>
    <a href="<?= $attr($home . '/account') ?>">My account</a>
  </span>
</footer>
<?php else: ?>
<footer class="footer" role="contentinfo"><?= $esc($config['footer']) ?></footer>
<?php endif; ?>
