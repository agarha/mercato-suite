<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$vendor = $data['vendor'] ?? null;
$reason = (string) ($data['reason'] ?? '');
$kycLabel = static function (string $s): string {
    return match ($s) {
        'verified'   => 'Verified',
        'pending'    => 'Pending review',
        'failed'     => 'Failed — please re-submit',
        'not_started'=> 'Not started',
        default      => ucfirst(str_replace('_', ' ', $s)),
    };
};
$stars = static function (float $r): string {
    $f = (int) floor($r); $h = ($r - $f) >= 0.5 ? 1 : 0; $e = 5 - $f - $h;
    return str_repeat('★', $f) . str_repeat('⯨', $h) . str_repeat('☆', $e);
};
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Provider dashboard — <?= $esc($config['brand']) ?></title>
  <meta name="robots" content="noindex">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront.css')) ?>">
  <?php if (($theme ?? '') === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront-taskfirst.css')) ?>"><?php endif; ?>
</head>
<body<?= ($theme ?? '') === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero" aria-labelledby="pd-heading">
        <div>
          <div class="eyebrow">Provider dashboard</div>
          <?php if ($vendor === null && $reason === 'not_signed_in'): ?>
            <h1 id="pd-heading">Sign in to access your provider dashboard</h1>
            <p>Manage your services, see incoming jobs, track payouts, and respond to reviews.</p>
            <div class="hero-actions">
              <a class="button" href="<?= $attr('/wp-login.php?redirect_to=' . urlencode($home . '/provider/dashboard')) ?>">Sign in</a>
              <a class="button secondary" href="<?= $attr('/wp-login.php?action=register') ?>">Create account</a>
            </div>
          <?php elseif ($vendor === null): ?>
            <h1 id="pd-heading">You're signed in but not registered as a provider</h1>
            <p>Apply to provide services on <?= $esc($config['brand']) ?>. Approval includes a quick KYC and Stripe Connect setup so you can be paid.</p>
            <div class="hero-actions">
              <a class="button" href="/wp-admin/admin.php?page=mercato-vendor">Open vendor console</a>
              <a class="button secondary" href="<?= $attr($home) ?>">Back to <?= $esc($config['brand']) ?></a>
            </div>
          <?php else: ?>
            <h1 id="pd-heading"><?= $esc($vendor['business_name']) ?></h1>
            <p>
              @<?= $esc($vendor['store_slug']) ?>
              · Status: <strong><?= $esc(ucfirst((string) $vendor['status'])) ?></strong>
              <?php if ((int) $data['review_count'] > 0): ?>
                · <span aria-label="rating <?= $attr(number_format((float) $data['review_average'], 1)) ?> out of 5"><?= $stars((float) $data['review_average']) ?></span>
                <strong><?= number_format((float) $data['review_average'], 1) ?></strong>
                <span style="color:var(--muted)">(<?= (int) $data['review_count'] ?>)</span>
              <?php endif; ?>
            </p>
            <div class="hero-actions">
              <a class="button" href="<?= $attr($home . '/providers/' . $vendor['store_slug']) ?>">View public profile</a>
              <a class="button secondary" href="/wp-admin/admin.php?page=mercato-vendor">Open vendor console</a>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($vendor !== null): ?>
          <aside class="hero-media" aria-label="Snapshot">
            <div class="booking-panel">
              <h3>At a glance</h3>
              <div class="cart-line"><span>Active services</span><strong><?= (int) $data['services_count'] ?></strong></div>
              <div class="cart-line"><span>Total jobs</span><strong><?= (int) $data['jobs_count'] ?></strong></div>
              <div class="cart-line"><span>KYC</span><strong><?= $esc($kycLabel((string) $data['kyc_status'])) ?></strong></div>
              <div class="cart-line"><span>Payouts</span><strong><?= $vendor['stripe_account_id'] ? 'Stripe Connect' : 'Pending setup' ?></strong></div>
              <?php if (!empty($data['latest_payout'])): ?>
                <div class="cart-line">
                  <span>Last batch</span>
                  <strong>#<?= $esc($data['latest_payout']['batch_id']) ?> · <?= $esc($data['latest_payout']['status']) ?> · <?= $money($data['latest_payout']['total_minor']) ?></strong>
                </div>
              <?php endif; ?>
            </div>
          </aside>
        <?php endif; ?>
      </section>
    </div>

    <?php if ($vendor !== null): ?>
      <section class="section">
        <div class="section-head"><div><h2>Recent jobs</h2><p>Latest 10 jobs assigned to your business.</p></div></div>
        <div class="account-panel">
          <table class="table">
            <thead><tr><th>Job</th><th>Status</th><th>Assignee</th><th>Updated</th></tr></thead>
            <tbody>
              <?php if (empty($data['recent_jobs'])): ?>
                <tr><td colspan="4">No jobs yet.</td></tr>
              <?php else: foreach ($data['recent_jobs'] as $job):
                $assignee = (int) ($job['assigned_user_id'] ?? 0); ?>
                <tr>
                  <td>#<?= $esc($job['job_id']) ?></td>
                  <td><?= $esc($job['status']) ?></td>
                  <td><?= $assignee > 0 ? $esc($assignee) : 'Unassigned' ?></td>
                  <td><?= $esc($job['updated_at']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section" id="reviews">
        <div class="section-head">
          <div><h2>Recent reviews</h2><p>Latest 5 published reviews from buyers.</p></div>
          <?php if ((int) $data['review_count'] > 0): ?>
            <span class="pill"><?= $stars((float) $data['review_average']) ?> <?= number_format((float) $data['review_average'], 1) ?>/5 · <?= (int) $data['review_count'] ?> total</span>
          <?php endif; ?>
        </div>

        <?php if (empty($data['reviews'])): ?>
          <article class="empty-state">
            <h3>No reviews yet</h3>
            <p>Buyers can review your work after a job completes. Reviews appear here and on your public profile.</p>
          </article>
        <?php else: ?>
          <div class="positioning">
            <?php foreach ($data['reviews'] as $r): ?>
              <article class="positioning-card">
                <b aria-label="rating <?= $attr($r['rating']) ?> out of 5"><?= $stars((float) $r['rating']) ?></b>
                <?php if (!empty($r['title'])): ?>
                  <strong><?= $esc($r['title']) ?></strong>
                <?php endif; ?>
                <p><?= $esc($r['body'] ?: 'No comment provided.') ?></p>
                <p style="margin-top:12px;font-size:12px;color:var(--muted)">Buyer #<?= $esc($r['buyer_user_id']) ?> · <?= $esc($r['created_at']) ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
  <?php include $partials . '/footer.php'; ?>
</body>
</html>
