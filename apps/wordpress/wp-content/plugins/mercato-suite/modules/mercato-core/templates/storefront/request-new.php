<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
$preselectedProvider = isset($_GET['provider']) ? (string) $_GET['provider'] : '';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Post a request — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Post a service request and let providers bid on it.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <meta name="theme-color" content="#0a4f47">
  <link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront.css') ?>">
  <?php if (($theme ?? '') === 'taskfirst'): ?><link rel="stylesheet" href="<?= $attr($asset_url . '/css/storefront-taskfirst.css') ?>"><?php endif; ?>
</head>
<body<?= ($theme ?? '') === 'taskfirst' ? ' class="dir-taskfirst"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include $partials . '/header.php'; ?>
  <main id="main" tabindex="-1">
    <div class="hero-wrap">
      <section class="hero">
        <div>
          <div class="eyebrow">Post a request</div>
          <h1>Tell providers what you need</h1>
          <p>Share the scope, location, and budget. Approved providers will respond with sealed or open bids — you pick the winner.</p>
        </div>
        <aside class="hero-media">
          <div class="booking-panel">
            <h3>How it works</h3>
            <div class="cart-line"><span>1. Post your request</span><strong>Free</strong></div>
            <div class="cart-line"><span>2. Receive provider bids</span><strong>Usually within 24h</strong></div>
            <div class="cart-line"><span>3. Accept a bid</span><strong>Creates the job</strong></div>
            <div class="cart-line"><span>4. Pay on completion</span><strong>Via Stripe</strong></div>
          </div>
        </aside>
      </section>
    </div>

    <section class="section">
      <div class="account-panel" style="max-width:760px;margin:0 auto">
        <h3>New service request</h3>
        <form id="mercato-new-request" method="post" action="/wp-json/mercato/v1/service-requests" onsubmit="return submitMercatoRequest(event)">
          <div class="cart-line"><label for="title" style="flex:1">Title<br><input id="title" name="title" required maxlength="160" style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px"></label></div>
          <div class="cart-line">
            <label for="category_id" style="flex:1">Category<br>
              <select id="category_id" name="category_id" required style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px">
                <option value="">Pick a category…</option>
                <?php foreach ($data['categories'] as $category): ?>
                  <option value="<?= $attr($category['category_id']) ?>"><?= $esc($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="cart-line"><label for="description" style="flex:1">Description<br><textarea id="description" name="description" required rows="4" style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px"></textarea></label></div>
          <div class="cart-line" style="gap:12px">
            <label for="city" style="flex:1">City<br><input id="city" name="city" required style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px"></label>
            <label for="region" style="flex:1">Region<br><input id="region" name="region" style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px"></label>
          </div>
          <div class="cart-line" style="gap:12px">
            <label for="budget_max" style="flex:1">Max budget (in dollars)<br><input id="budget_max" name="budget_max" type="number" min="0" step="1" required style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px"></label>
            <label for="bid_mode" style="flex:1">Bid mode<br>
              <select id="bid_mode" name="bid_mode" required style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-top:6px">
                <option value="sealed">Sealed bid</option>
                <option value="open">Open auction</option>
              </select>
            </label>
          </div>
          <?php if ($preselectedProvider !== ''): ?>
            <input type="hidden" name="invite_provider_slug" value="<?= $attr($preselectedProvider) ?>">
            <p style="color:var(--muted);font-size:13px;margin:8px 0 0">Invite-only request for provider <strong><?= $esc($preselectedProvider) ?></strong>.</p>
          <?php endif; ?>
          <div class="hero-actions" style="margin-top:18px">
            <button type="submit" class="button">Post request</button>
            <a class="button secondary" href="/t/<?= $attr($config['tenant_slug'] ?? 'gigsii') ?>/services">Cancel</a>
          </div>
          <p id="mercato-request-status" role="status" style="margin-top:12px;color:var(--muted);font-size:13px"></p>
        </form>
      </div>
    </section>
  </main>
  <?php include $partials . '/footer.php'; ?>
  <script>
    function submitMercatoRequest(ev) {
      ev.preventDefault();
      var form = ev.target;
      var status = document.getElementById('mercato-request-status');
      status.textContent = 'Submitting…';
      var body = Object.fromEntries(new FormData(form).entries());
      body.budget_max_minor = Math.round(parseFloat(body.budget_max || '0') * 100);
      delete body.budget_max;
      fetch(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
        .then(function(res) {
          if (res.ok) {
            status.style.color = '#15803d';
            status.textContent = 'Request posted. Providers will be notified.';
            form.reset();
          } else {
            status.style.color = '#dc2626';
            status.textContent = (res.json && res.json.message) || 'Could not post request.';
          }
        })
        .catch(function() {
          status.style.color = '#dc2626';
          status.textContent = 'Network error. Try again.';
        });
      return false;
    }
  </script>
</body>
</html>
