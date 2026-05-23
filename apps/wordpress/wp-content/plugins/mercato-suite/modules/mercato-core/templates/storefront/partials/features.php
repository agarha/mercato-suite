<?php /** @var array $data */ /** @var \Closure $esc */
$enabledCount = 0;
foreach ($data['feature_flags'] as $flag) { if ((int) ($flag['enabled'] ?? 0) === 1) { $enabledCount++; } } ?>
<section class="section" id="features">
  <div class="section-head">
    <div><h2>All Gigsii capabilities enabled</h2><p>This tenant is configured to exercise every Mercato module and Gigsii-specific capability in the local environment.</p></div>
    <span class="pill"><?= $enabledCount ?> enabled flags</span>
  </div>
  <div class="feature-cloud">
    <?php if (empty($data['feature_flags'])): ?>
      <span class="disabled">No tenant feature flags configured</span>
    <?php else: foreach ($data['feature_flags'] as $flag): $on = (int) ($flag['enabled'] ?? 0) === 1; ?>
      <span class="<?= $on ? 'enabled' : 'disabled' ?>"><?= $esc($flag['feature_key']) ?></span>
    <?php endforeach; endif; ?>
  </div>
  <div class="feature-cloud">
    <?php if (empty($data['integrations'])): ?>
      <span class="disabled">No integrations configured</span>
    <?php else: foreach ($data['integrations'] as $integration): $cls = (string) $integration['status'] === 'disabled' ? 'disabled' : 'enabled'; ?>
      <span class="<?= $cls ?>"><?= $esc($integration['provider_key']) ?>: <?= $esc($integration['status']) ?></span>
    <?php endforeach; endif; ?>
  </div>
</section>
