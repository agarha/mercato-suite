<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$theme = (string) ($theme ?? '');
$token = (string) ($data['token'] ?? '');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify email — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Confirm your email to complete your <?= $attr($config['brand']) ?> application.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront.css')) ?>">
  <?php if ($theme === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css?v=' . @filemtime(MERCATO_SUITE_DIR . '/modules/mercato-core/assets/css/storefront-taskfirst.css')) ?>"><?php endif; ?>
</head>
<body<?= $theme === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <section class="section verify-section" style="max-width:520px;margin:60px auto;">
      <div id="verify-card" class="signup-success">
        <h2 id="verify-title">Confirming your email...</h2>
        <p id="verify-body">Hold on while we verify the link.</p>
        <p id="verify-actions" hidden>
          <a class="button" href="<?= $attr($home . '/provider/dashboard') ?>">Open my dashboard</a>
        </p>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
<script>
(function () {
  var token = <?= json_encode($token) ?>;
  var tenantHome = <?= json_encode($home) ?>;
  var $title = document.getElementById('verify-title');
  var $body = document.getElementById('verify-body');
  var $actions = document.getElementById('verify-actions');
  if (!token) {
    $title.textContent = 'Missing verification link';
    $body.textContent = 'The verification link in your email looks incomplete. Open the original message and click the link again.';
    return;
  }
  fetch(tenantHome + '/?rest_route=/mercato/v1/storefront/signup/verify', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: token })
  }).then(function (r) {
    return r.json().then(function (body) { return { ok: r.ok, body: body }; });
  }).then(function (resp) {
    if (!resp.ok) {
      $title.textContent = 'Could not verify';
      $body.textContent = (resp.body && resp.body.message) || 'The link is invalid or expired. Try requesting a new verification email from your dashboard.';
      return;
    }
    $title.textContent = 'Email confirmed!';
    $body.textContent = 'Thanks. Your provider profile is now waiting for tenant review. We typically respond within two business days.';
    $actions.hidden = false;
  }).catch(function () {
    $title.textContent = 'Network error';
    $body.textContent = 'Could not reach the server. Please try again in a moment.';
  });
})();
</script>
</body>
</html>
