<?php /** @var array $config */ /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $attr */ /** @var \Closure $money */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii'); ?>
<section class="section" id="requests">
  <div class="section-head">
    <div><h2>Post a request and let providers bid</h2><p>Clients can publish a service request, then approved providers can submit sealed bids or open-auction offers.</p></div>
    <a class="button" href="<?= $attr($home . '/requests/new') ?>">Post a request</a>
  </div>
  <div class="ops-grid">
    <div class="ops-score">
      <div><span>Requests</span><strong><?= (int) $data['request_count'] ?></strong></div>
      <div><span>Provider bids</span><strong><?= (int) $data['bid_count'] ?></strong></div>
      <div><span>Bid modes</span><strong>2</strong></div>
      <div><span>Award creates job</span><strong>Yes</strong></div>
    </div>
    <div class="account-panel">
      <h3>Recent service requests</h3>
      <table class="table">
        <thead><tr><th>Request</th><th>Title</th><th>Location</th><th>Mode</th><th>Budget</th><th>Bids</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($data['requests'])): ?>
            <tr><td colspan="7">No client service requests yet.</td></tr>
          <?php else: foreach ($data['requests'] as $req):
            $loc = trim((string) ($req['city'] ?? '') . ', ' . (string) ($req['region'] ?? ''), ' ,'); ?>
            <tr>
              <td>#<?= $esc($req['request_id']) ?></td>
              <td><?= $esc($req['title']) ?></td>
              <td><?= $esc($loc === '' ? 'Remote/local' : $loc) ?></td>
              <td><?= $esc($req['bid_mode']) ?></td>
              <td><?= $money($req['budget_max_minor']) ?> <?= $esc($req['currency']) ?></td>
              <td><?= $esc($req['bid_count']) ?></td>
              <td><?= $esc($req['status']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
