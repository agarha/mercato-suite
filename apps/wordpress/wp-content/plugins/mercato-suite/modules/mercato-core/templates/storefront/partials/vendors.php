<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ ?>
<section class="section" id="vendors">
  <div class="section-head">
    <div><h2><?= $esc($config['vendor_headline']) ?></h2><p><?= $esc($config['vendor_copy']) ?></p></div>
    <span class="pill"><?= $esc($config['vendor_badge']) ?></span>
  </div>
  <div class="vendor-grid">
    <?php foreach ($data['vendors'] as $vendor): ?>
      <article class="vendor-card">
        <div class="vendor-avatar"><?= $esc(mb_substr((string) $vendor['business_name'], 0, 1)) ?></div>
        <div>
          <h3><?= $esc($vendor['business_name']) ?></h3>
          <p>@<?= $esc($vendor['store_slug']) ?></p>
          <span><?= $esc($vendor['status']) ?> / <?= $esc($config['vendor_status_label']) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
