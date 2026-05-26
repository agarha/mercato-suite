<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $money */ ?>
<section class="section" id="shop">
  <div class="section-head">
    <div><h2><?= $esc($config['catalog_headline']) ?></h2><p><?= $esc($config['catalog_copy']) ?></p></div>
    <span class="pill"><?= $esc($config['catalog_badge']) ?></span>
  </div>
  <div class="product-grid">
    <?php if (empty($data['products'])): ?>
      <article class="empty-state"><h3><?= $esc($config['item_empty_title']) ?></h3><p><?= $esc($config['item_empty_copy']) ?></p></article>
    <?php else: foreach ($data['products'] as $index => $product):
      $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4]; ?>
      <article class="product-card">
        <div class="product-media <?= $tone ?>"><span><?= $esc(mb_substr((string) $product['title'], 0, 1)) ?></span><small>Verified local service</small></div>
        <div class="product-body">
          <p class="vendor-name"><?= $esc($product['business_name']) ?></p>
          <h3><?= $esc($product['title']) ?></h3>
          <p><?= $esc($product['description'] ?: $config['item_fallback_copy']) ?></p>
          <div class="service-tags"><span>Insured</span><span>Fast response</span><span>Local</span></div>
          <div class="product-meta">
            <strong><?= $money($product['price_minor']) ?></strong>
            <span><?= $esc($product['stock_quantity']) ?> <?= $esc($config['item_quantity_label']) ?></span>
          </div>
        </div>
      </article>
    <?php endforeach; endif; ?>
  </div>
</section>
