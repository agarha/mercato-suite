<?php /** @var array $config */ /** @var \Closure $esc */ ?>
<section class="section">
  <div class="section-head">
    <div><h2><?= $esc($config['positioning_headline']) ?></h2><p><?= $esc($config['positioning_copy']) ?></p></div>
  </div>
  <div class="positioning">
    <?php foreach ((array) ($config['positioning_cards'] ?? []) as $card): if (!is_array($card)) continue; ?>
      <div class="positioning-card">
        <b><?= $esc($card['eyebrow'] ?? '') ?></b>
        <strong><?= $esc($card['title'] ?? '') ?></strong>
        <p><?= $esc($card['copy'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
