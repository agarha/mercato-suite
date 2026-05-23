<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $money */ ?>
<aside class="demo-board">
  <div class="board-row"><span><?= $esc($config['metric_labels']['vendors'] ?? '') ?></span><strong><?= (int) $data['vendor_count'] ?></strong></div>
  <div class="board-row"><span><?= $esc($config['metric_labels']['products'] ?? '') ?></span><strong><?= (int) $data['product_count'] ?></strong></div>
  <div class="board-row"><span><?= $esc($config['metric_labels']['orders'] ?? '') ?></span><strong><?= (int) $data['suborder_count'] ?></strong></div>
  <div class="board-row"><span><?= $esc($config['metric_labels']['take'] ?? '') ?></span><strong><?= $money($data['take_rate_minor']) ?></strong></div>
</aside>
