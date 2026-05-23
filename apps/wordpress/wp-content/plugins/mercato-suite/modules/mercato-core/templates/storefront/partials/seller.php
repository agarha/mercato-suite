<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $money */
$payout = $data['latest_payout'];
$notification = $data['latest_notification'];
$payoutSummary = empty($payout)
  ? 'No payout batch yet'
  : 'Batch #' . $esc($payout['batch_id']) . ' is ' . $esc($payout['status']) . ' for ' . $money($payout['total_minor']);
$notificationSummary = empty($notification)
  ? 'No notification yet'
  : 'Delivery #' . $esc($notification['delivery_id']) . ' sent to ' . $esc($notification['recipient']); ?>
<section class="section" id="seller">
  <div class="section-head">
    <div><h2><?= $esc($config['seller_headline']) ?></h2><p><?= $esc($config['seller_copy']) ?></p></div>
    <a class="button secondary" href="/wp-admin/admin.php?page=mercato-vendor"><?= $esc($config['secondary_cta']) ?></a>
  </div>
  <div class="seller-grid">
    <?php foreach ((array) ($config['seller_steps'] ?? []) as $step): if (!is_array($step)) continue;
      $title = (string) ($step['title'] ?? '');
      if ($title === '__PAYOUT_SUMMARY__') { $title = $payoutSummary; }
      elseif ($title === '__NOTIFICATION_SUMMARY__') { $title = $notificationSummary; } ?>
      <div class="step">
        <b><?= $esc($step['eyebrow'] ?? '') ?></b>
        <strong><?= $title /* already escaped above OR safe constant copy */ ?></strong>
        <p><?= $esc($step['copy'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
