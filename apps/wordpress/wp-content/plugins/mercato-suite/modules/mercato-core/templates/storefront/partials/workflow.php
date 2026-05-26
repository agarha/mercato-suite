<?php /** @var array $config */ /** @var \Closure $esc */ ?>
<section class="section">
  <div class="section-head">
    <div><h2><?= $esc($config['workflow_headline']) ?></h2><p><?= $esc($config['workflow_copy']) ?></p></div>
  </div>
  <div class="workflow">
    <?php foreach ((array) ($config['workflow_steps'] ?? []) as $step): if (!is_array($step)) continue; ?>
      <div class="step">
        <b><?= $esc($step['eyebrow'] ?? '') ?></b>
        <strong><?= $esc($step['title'] ?? '') ?></strong>
        <p><?= $esc($step['copy'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
