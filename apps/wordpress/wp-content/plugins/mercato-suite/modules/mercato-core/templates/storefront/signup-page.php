<?php
/** @var array<string,mixed> $config */
/** @var array<string,mixed> $data */
/** @var string $asset_url */
/** @var string $partials */
/** @var \Closure $esc */
/** @var \Closure $attr */
/** @var \Closure $money */
/** @var string $current_page */
$home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
$theme = (string) ($theme ?? '');

// Categories arrive flat with parent_name so we can group them client-side.
$cats = $data['categories'] ?? [];
$approvedCount = (int) ($data['approved_provider_count'] ?? 0);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Become a Pro — <?= $esc($config['brand']) ?></title>
  <meta name="description" content="Apply to join <?= $attr($config['brand']) ?> as a trusted local pro. Free signup, geo-aware service zones, multi-service catalog.">
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
    <div class="hero-wrap">
      <section class="hero" aria-labelledby="signup-heading">
        <div>
          <div class="eyebrow">Become a Pro</div>
          <h1 id="signup-heading">Earn on your terms<br><em>in your neighbourhood</em></h1>
          <p>Join <?= (int) $approvedCount ?> verified pros already serving customers through <?= $esc($config['brand']) ?>. Set your own rates, pick the jobs you want, and grow a steady book of business — backed by our payments + payouts platform.</p>
          <ul class="signup-perks" aria-label="What you get">
            <li><span aria-hidden="true">✓</span> Direct customer leads with budget set upfront</li>
            <li><span aria-hidden="true">✓</span> You control which services you offer and where</li>
            <li><span aria-hidden="true">✓</span> Verified-pro badge once your KYC + background check clears</li>
            <li><span aria-hidden="true">✓</span> Stripe-powered payouts straight to your bank</li>
          </ul>
        </div>
        <aside class="hero-media" aria-label="Application progress">
          <div class="booking-panel signup-progress" role="status" aria-live="polite">
            <h3>Application progress</h3>
            <ol class="signup-steps">
              <li data-step="1" class="is-active"><strong>1</strong> Account</li>
              <li data-step="2"><strong>2</strong> Business profile</li>
              <li data-step="3"><strong>3</strong> Services</li>
              <li data-step="4"><strong>4</strong> Service area</li>
              <li data-step="5"><strong>5</strong> Review &amp; submit</li>
            </ol>
            <p class="signup-progress-meta"><span id="signup-progress-text">Step 1 of 5</span></p>
          </div>
        </aside>
      </section>
    </div>

    <section class="section">
      <form id="signup-form" class="signup-form" novalidate>
        <!-- STEP 1: account -->
        <fieldset class="signup-step is-active" data-step="1" aria-labelledby="signup-step1-title">
          <legend id="signup-step1-title" class="signup-step-title">Create your account</legend>
          <p class="signup-step-help">Already a <?= $esc($config['brand']) ?> customer? <a href="<?= $attr($home . '/account') ?>">Sign in instead</a> &mdash; we'll attach the application to your existing account.</p>
          <div class="signup-row">
            <label class="signup-field">
              <span>First name *</span>
              <input type="text" name="account[first_name]" autocomplete="given-name" required>
            </label>
            <label class="signup-field">
              <span>Last name *</span>
              <input type="text" name="account[last_name]" autocomplete="family-name" required>
            </label>
          </div>
          <label class="signup-field">
            <span>Email *</span>
            <input type="email" name="account[email]" autocomplete="email" required>
            <small>We'll send all customer enquiries and payout notifications here.</small>
          </label>
          <label class="signup-field">
            <span>Password *</span>
            <input type="password" name="account[password]" autocomplete="new-password" minlength="8" required>
            <small>At least 8 characters.</small>
          </label>
          <label class="signup-field">
            <span>Mobile phone *</span>
            <input type="tel" name="business[phone]" autocomplete="tel" required>
            <small>So we can verify your identity and customers can text you en-route.</small>
          </label>
        </fieldset>

        <!-- STEP 2: business profile -->
        <fieldset class="signup-step" data-step="2" aria-labelledby="signup-step2-title" hidden>
          <legend id="signup-step2-title" class="signup-step-title">Tell customers about your business</legend>
          <label class="signup-field">
            <span>Business or trading name *</span>
            <input type="text" name="business[business_name]" required>
            <small>Solo? Use your full name (e.g. "Jane Cooper — Cleaning &amp; Organising").</small>
          </label>
          <label class="signup-field">
            <span>Headline</span>
            <input type="text" name="business[headline]" maxlength="120" placeholder="Reliable handyman, 10+ years, no callout fee.">
            <small>One line that shows under your name on search results.</small>
          </label>
          <label class="signup-field">
            <span>About you</span>
            <textarea name="business[bio]" rows="4" maxlength="800" placeholder="Tell customers what makes you the right pick."></textarea>
          </label>
          <div class="signup-row">
            <label class="signup-field">
              <span>Years of experience</span>
              <input type="number" name="business[years_experience]" min="0" max="60" step="1">
            </label>
            <label class="signup-field">
              <span>Languages spoken</span>
              <input type="text" name="business[languages]" placeholder="English, Spanish">
            </label>
          </div>
          <div class="signup-row">
            <label class="signup-field">
              <span>Standard hourly rate</span>
              <input type="number" name="business[hourly_rate]" min="0" step="1" placeholder="65">
              <small>You can override per-service in step 3.</small>
            </label>
            <label class="signup-field">
              <span>License number</span>
              <input type="text" name="business[license_number]">
            </label>
          </div>
          <div class="signup-row">
            <label class="signup-field">
              <span>Insurance carrier</span>
              <input type="text" name="business[insurance_carrier]">
            </label>
            <label class="signup-field">
              <span>Liability coverage</span>
              <input type="number" name="business[insurance_amount]" min="0" step="1000" placeholder="2000000">
              <small>USD. Most platforms require $2M minimum.</small>
            </label>
          </div>
        </fieldset>

        <!-- STEP 3: services -->
        <fieldset class="signup-step" data-step="3" aria-labelledby="signup-step3-title" hidden>
          <legend id="signup-step3-title" class="signup-step-title">What services do you offer?</legend>
          <p class="signup-step-help">Add one service to get started — you can add up to 30 from your provider dashboard later.</p>
          <div id="signup-services" class="signup-services">
            <!-- service row template — JS clones on Add another -->
            <div class="signup-service" data-service-row>
              <div class="signup-row">
                <label class="signup-field">
                  <span>Category *</span>
                  <select name="services[0][category_id]" data-name-pattern="services[INDEX][category_id]" required>
                    <option value="">Pick a category…</option>
                    <?php foreach ($cats as $cat): ?>
                      <option value="<?= $attr($cat['category_id']) ?>">
                        <?= $esc(($cat['parent_name'] ? $cat['parent_name'] . ' • ' : '') . $cat['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="signup-field">
                  <span>Pricing type *</span>
                  <select name="services[0][pricing_type]" data-name-pattern="services[INDEX][pricing_type]" required>
                    <option value="hourly">Hourly rate</option>
                    <option value="fixed">Fixed per job</option>
                    <option value="per_unit">Per unit (e.g. per window)</option>
                    <option value="quote_required">Quote on request</option>
                  </select>
                </label>
              </div>
              <label class="signup-field">
                <span>Service title *</span>
                <input type="text" name="services[0][title]" data-name-pattern="services[INDEX][title]" required placeholder="Standard home cleaning (3 bed)">
              </label>
              <label class="signup-field">
                <span>What's included</span>
                <textarea name="services[0][description]" data-name-pattern="services[INDEX][description]" rows="2" maxlength="500"></textarea>
              </label>
              <div class="signup-row">
                <label class="signup-field">
                  <span>Price *</span>
                  <input type="number" name="services[0][price]" data-name-pattern="services[INDEX][price]" min="0" step="1" required placeholder="120">
                  <small>For "Hourly" enter your hourly rate; for "Fixed" the total.</small>
                </label>
                <label class="signup-field">
                  <span>Estimated minutes</span>
                  <input type="number" name="services[0][duration_minutes]" data-name-pattern="services[INDEX][duration_minutes]" min="15" step="15" placeholder="180">
                </label>
              </div>
            </div>
          </div>
          <button type="button" class="button secondary" id="signup-add-service">+ Add another service</button>
        </fieldset>

        <!-- STEP 4: service area (cascading dropdowns from /geo/regions) -->
        <fieldset class="signup-step" data-step="4" aria-labelledby="signup-step4-title" hidden>
          <legend id="signup-step4-title" class="signup-step-title">Where do you work?</legend>
          <p class="signup-step-help">Pick where you serve customers. Pros within a customer's radius show up first in their search. You can add more zones from your dashboard later.</p>

          <div class="signup-row">
            <label class="signup-field">
              <span>Country *</span>
              <select name="location[country_slug]" id="signup-country" required></select>
              <input type="hidden" name="location[country]" id="signup-country-code">
            </label>
            <label class="signup-field">
              <span>Province / state *</span>
              <select name="location[region_slug]" id="signup-province" required disabled>
                <option value="">Pick a country first</option>
              </select>
              <input type="hidden" name="location[region]" id="signup-region-name">
            </label>
          </div>

          <div class="signup-row">
            <label class="signup-field">
              <span>City *</span>
              <select name="location[city_slug]" id="signup-city-select" required disabled>
                <option value="">Pick a province first</option>
              </select>
              <input type="hidden" name="location[city]" id="signup-city">
            </label>
            <label class="signup-field">
              <span>Neighborhood <small>(optional)</small></span>
              <select name="location[neighborhood_slug]" id="signup-neighborhood" disabled>
                <option value="">Pick a city first</option>
              </select>
            </label>
          </div>

          <div class="signup-row">
            <label class="signup-field">
              <span>Postal code <small>(optional)</small></span>
              <input type="text" name="location[postal_code]" autocomplete="postal-code" id="signup-postcode" placeholder="M5V 1A1">
            </label>
            <label class="signup-field">
              <span>Travel radius *</span>
              <input type="number" name="location[service_radius_km]" min="1" max="200" step="1" value="25" id="signup-radius" required>
              <small>Kilometres from your base location.</small>
            </label>
          </div>

          <input type="hidden" name="location[latitude]" id="signup-lat">
          <input type="hidden" name="location[longitude]" id="signup-lng">

          <div class="signup-geo-actions">
            <button type="button" class="button secondary" id="signup-geolocate">
              <span aria-hidden="true">&#x1F4CD;</span> Use my current location instead
            </button>
            <p class="signup-geo-status" id="signup-geo-status" role="status" aria-live="polite"></p>
          </div>
        </fieldset>

        <!-- STEP 5: review -->
        <fieldset class="signup-step" data-step="5" aria-labelledby="signup-step5-title" hidden>
          <legend id="signup-step5-title" class="signup-step-title">Review &amp; submit</legend>
          <p class="signup-step-help">Take a moment to double-check. We'll review your application within 2 business days.</p>
          <div id="signup-review" class="signup-review"></div>
          <label class="signup-field signup-consent">
            <input type="checkbox" name="consent" required>
            <span>I agree to the <a href="#">Pro Terms</a> and to a background check + identity verification.</span>
          </label>
        </fieldset>

        <div class="signup-actions">
          <button type="button" class="button ghost" id="signup-back" hidden>← Back</button>
          <button type="button" class="button" id="signup-next">Continue →</button>
          <button type="submit" class="button" id="signup-submit" hidden>Submit application</button>
        </div>

        <p id="signup-error" class="signup-error" role="alert" hidden></p>
      </form>

      <!-- success state shown after successful POST -->
      <div id="signup-success" class="signup-success" hidden>
        <h2>Thanks — you're on the list! 🎉</h2>
        <p>We've created your draft profile. Watch your inbox for the verification email and a note from your city manager.</p>
        <p><a class="button" href="<?= $attr($home . '/provider/dashboard') ?>">Open my dashboard</a></p>
      </div>
    </section>
  </main>

  <?php include $partials . '/footer.php'; ?>

<script>
(function () {
  var form = document.getElementById('signup-form');
  if (!form) return;
  var steps = form.querySelectorAll('.signup-step');
  var progressNodes = document.querySelectorAll('.signup-steps li');
  var progressText = document.getElementById('signup-progress-text');
  var nextBtn = document.getElementById('signup-next');
  var backBtn = document.getElementById('signup-back');
  var submitBtn = document.getElementById('signup-submit');
  var errorBox = document.getElementById('signup-error');
  var successBox = document.getElementById('signup-success');
  var reviewBox = document.getElementById('signup-review');
  var current = 1;
  var total = steps.length;

  function show(step) {
    steps.forEach(function (s) {
      var n = parseInt(s.getAttribute('data-step'), 10);
      s.hidden = n !== step;
      s.classList.toggle('is-active', n === step);
    });
    progressNodes.forEach(function (li) {
      var n = parseInt(li.getAttribute('data-step'), 10);
      li.classList.toggle('is-active', n === step);
      li.classList.toggle('is-done', n < step);
    });
    if (progressText) progressText.textContent = 'Step ' + step + ' of ' + total;
    backBtn.hidden = step === 1;
    nextBtn.hidden = step === total;
    submitBtn.hidden = step !== total;
    if (step === total) renderReview();
    window.scrollTo({top: form.offsetTop - 24, behavior: 'smooth'});
  }

  function currentFieldset() {
    return form.querySelector('.signup-step[data-step="' + current + '"]');
  }

  function validateStep() {
    var fs = currentFieldset();
    var inputs = fs.querySelectorAll('input,select,textarea');
    for (var i = 0; i < inputs.length; i++) {
      if (!inputs[i].checkValidity()) {
        inputs[i].reportValidity();
        return false;
      }
    }
    return true;
  }

  nextBtn.addEventListener('click', function () {
    if (!validateStep()) return;
    if (current < total) { current++; show(current); }
  });
  backBtn.addEventListener('click', function () {
    if (current > 1) { current--; show(current); }
  });

  // Add-another-service handler
  var servicesWrap = document.getElementById('signup-services');
  var addBtn = document.getElementById('signup-add-service');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      var rows = servicesWrap.querySelectorAll('[data-service-row]');
      var next = rows.length;
      var clone = rows[0].cloneNode(true);
      clone.querySelectorAll('[data-name-pattern]').forEach(function (el) {
        el.name = el.getAttribute('data-name-pattern').replace('INDEX', next);
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
      });
      servicesWrap.appendChild(clone);
    });
  }

  // Cascading geo dropdowns — country -> province -> city -> neighborhood.
  // Each select fetches its children from /mercato/v1/geo/regions and copies
  // the chosen row's lat/lng + recommended radius into the hidden inputs the
  // signup payload uses, so geocoding is unnecessary unless the user clicks
  // "Use my current location instead".
  (function () {
    // Derive tenant prefix from the current path (/t/<slug>/...). The REST
    // route must be tenant-scoped so the response includes the correct
    // geo_regions rows; /wp-json/... resolves to the default tenant which
    // doesn't have the Canadian seed.
    var m = location.pathname.match(/^(\/t\/[^\/]+)/);
    var tenantHome = m ? m[1] : '';
    var geoBase = tenantHome + '/?rest_route=/mercato/v1/geo/regions';
    var $country = document.getElementById('signup-country');
    var $province = document.getElementById('signup-province');
    var $city = document.getElementById('signup-city-select');
    var $hood = document.getElementById('signup-neighborhood');
    var $latH = document.getElementById('signup-lat');
    var $lngH = document.getElementById('signup-lng');
    var $countryCode = document.getElementById('signup-country-code');
    var $regionName = document.getElementById('signup-region-name');
    var $cityName = document.getElementById('signup-city');
    var $radius = document.getElementById('signup-radius');
    if (!$country) return;

    function clearSelect(el, placeholder) {
      el.innerHTML = '<option value="">' + placeholder + '</option>';
      el.disabled = true;
    }
    function fillSelect(el, rows, defaultLabel) {
      el.innerHTML = '<option value="">' + defaultLabel + '</option>';
      rows.forEach(function (r) {
        var opt = document.createElement('option');
        opt.value = r.slug;
        opt.textContent = r.name;
        opt.dataset.lat = r.latitude || '';
        opt.dataset.lng = r.longitude || '';
        opt.dataset.radius = r.radius_km || '';
        opt.dataset.code = r.code || '';
        opt.dataset.regionId = r.region_id;
        el.appendChild(opt);
      });
      el.disabled = rows.length === 0;
    }
    function fetchRegions(params) {
      var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
      // geoBase already contains "?rest_route=..." so we need "&" not "?".
      var sep = geoBase.indexOf('?') === -1 ? '?' : '&';
      return fetch(geoBase + sep + q, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function pickedOption(el) {
      return el.options[el.selectedIndex];
    }
    function applyPickedCoords(el) {
      var o = pickedOption(el);
      if (!o || !o.dataset.lat || !o.dataset.lng) return false;
      $latH.value = o.dataset.lat;
      $lngH.value = o.dataset.lng;
      if (o.dataset.radius && $radius && !$radius.dataset.userTouched) {
        $radius.value = o.dataset.radius;
      }
      return true;
    }

    // Step 1: load countries on first render.
    fetchRegions({ type: 'country' }).then(function (rows) {
      fillSelect($country, rows, 'Pick a country');
      // Auto-select if only one country (Gigsii ships with Canada only today).
      if (rows.length === 1) {
        $country.value = rows[0].slug;
        $country.dispatchEvent(new Event('change'));
      }
    });

    $country.addEventListener('change', function () {
      var o = pickedOption($country); if (!o || !o.value) return;
      $countryCode.value = o.dataset.code || '';
      applyPickedCoords($country);
      clearSelect($province, 'Loading...');
      clearSelect($city, 'Pick a province first');
      clearSelect($hood, 'Pick a city first');
      fetchRegions({ parent: o.value, type: 'province' }).then(function (rows) {
        fillSelect($province, rows, 'Pick a province');
      });
    });

    $province.addEventListener('change', function () {
      var o = pickedOption($province); if (!o || !o.value) return;
      $regionName.value = o.dataset.code || o.textContent || '';
      applyPickedCoords($province);
      clearSelect($city, 'Loading...');
      clearSelect($hood, 'Pick a city first');
      fetchRegions({ parent: o.value, type: 'city' }).then(function (rows) {
        fillSelect($city, rows, 'Pick a city');
      });
    });

    $city.addEventListener('change', function () {
      var o = pickedOption($city); if (!o || !o.value) return;
      $cityName.value = o.textContent;
      applyPickedCoords($city);
      clearSelect($hood, 'Loading...');
      fetchRegions({ parent: o.value, type: 'neighborhood' }).then(function (rows) {
        if (rows.length === 0) { clearSelect($hood, 'No neighborhoods listed (optional)'); return; }
        fillSelect($hood, rows, 'Any neighborhood');
      });
    });

    $hood.addEventListener('change', function () {
      applyPickedCoords($hood);
    });

    if ($radius) {
      $radius.addEventListener('input', function () { $radius.dataset.userTouched = '1'; });
    }
  })();

  // Geolocation: try browser API, fall back to manual postcode (Nominatim
  // geocodes on the server side when the form posts).
  var geoBtn = document.getElementById('signup-geolocate');
  if (geoBtn && navigator.geolocation) {
    geoBtn.addEventListener('click', function () {
      var status = document.getElementById('signup-geo-status');
      status.textContent = 'Locating…';
      navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('signup-lat').value = pos.coords.latitude.toFixed(7);
        document.getElementById('signup-lng').value = pos.coords.longitude.toFixed(7);
        status.textContent = '✓ Location captured (accuracy ±' + Math.round(pos.coords.accuracy) + 'm).';
      }, function (err) {
        status.textContent = 'Could not get location: ' + err.message + '. You can still continue — we\'ll use the postcode.';
      }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 });
    });
  }

  // Build a simple HTML review summary from the current form data.
  function renderReview() {
    var data = new FormData(form);
    var html = '';
    function row(label, value) {
      if (!value) return;
      html += '<div class="signup-review-row"><dt>' + label + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
    }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[m];
      });
    }
    html += '<dl>';
    row('Name', (data.get('account[first_name]') || '') + ' ' + (data.get('account[last_name]') || ''));
    row('Email', data.get('account[email]'));
    row('Phone', data.get('business[phone]'));
    row('Business', data.get('business[business_name]'));
    row('Headline', data.get('business[headline]'));
    row('Years experience', data.get('business[years_experience]'));
    row('Hourly rate', data.get('business[hourly_rate]'));
    row('License #', data.get('business[license_number]'));
    row('Insurance', data.get('business[insurance_carrier]') + ' — $' + (data.get('business[insurance_amount]') || '0'));
    row('Service area', (data.get('location[city]') || '') + ' (' + (data.get('location[postal_code]') || '') + ')');
    row('Radius', (data.get('location[service_radius_km]') || '') + ' km');
    html += '</dl>';

    // Services breakdown
    var rows = document.querySelectorAll('#signup-services [data-service-row]');
    if (rows.length) {
      html += '<h4>Services</h4><ul class="signup-review-services">';
      rows.forEach(function (r, i) {
        var title = r.querySelector('[name="services[' + i + '][title]"]');
        var pricing = r.querySelector('[name="services[' + i + '][pricing_type]"]');
        var price = r.querySelector('[name="services[' + i + '][price]"]');
        if (title && title.value) {
          html += '<li><strong>' + escapeHtml(title.value) + '</strong> — ' +
                  escapeHtml(pricing ? pricing.value : '') + ' @ $' + escapeHtml(price ? price.value : '') + '</li>';
        }
      });
      html += '</ul>';
    }
    reviewBox.innerHTML = html;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    submitBtn.disabled = true;
    errorBox.hidden = true;
    var payload = formToJson(form);
    var endpoint = (window.location.pathname.replace(/\/signup\/?$/, '') || '') + '/wp-json/mercato/v1/storefront/signup';
    // Fallback: WP REST always lives at /wp-json regardless of tenant prefix.
    endpoint = '/wp-json/mercato/v1/storefront/signup';
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': (window.wpApiSettings && window.wpApiSettings.nonce) || ''
      },
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.json().then(function (body) { return { ok: r.ok, body: body }; });
    }).then(function (resp) {
      if (!resp.ok) throw new Error(resp.body && resp.body.message ? resp.body.message : 'Submission failed');
      form.hidden = true;
      successBox.hidden = false;
      window.scrollTo({top: 0, behavior: 'smooth'});
    }).catch(function (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
      submitBtn.disabled = false;
    });
  });

  // Translate the flat FormData into the structured JSON the REST expects.
  function formToJson(formEl) {
    var data = new FormData(formEl);
    var out = { account: {}, business: {}, location: {}, services: [] };
    data.forEach(function (value, key) {
      if (key === 'consent') return;
      var m = key.match(/^([a-z_]+)\[(.+)\]$/);
      if (!m) return;
      var group = m[1], field = m[2];
      if (group === 'services') {
        var sm = field.match(/^(\d+)\]\[(.+)$/) || field.match(/^(\d+)\]\[(.+)\]?$/);
        // services[0][title] -> after first split key = 'services', field = '0][title' depending on browser
        // Normalize: handle both bracket forms.
        var idxMatch = key.match(/^services\[(\d+)\]\[([a-z_]+)\]$/);
        if (idxMatch) {
          var idx = parseInt(idxMatch[1], 10), name = idxMatch[2];
          if (!out.services[idx]) out.services[idx] = {};
          if (name === 'price') out.services[idx]['price_minor'] = Math.round(parseFloat(value || 0) * 100);
          else if (name === 'category_id') out.services[idx]['category_ids'] = [parseInt(value, 10)].filter(Boolean);
          else if (name === 'duration_minutes') out.services[idx][name] = parseInt(value, 10);
          else out.services[idx][name] = value;
        }
        return;
      }
      // Map hourly_rate/insurance_amount to _minor and lat/lng cast.
      if (group === 'business' && field === 'hourly_rate') {
        out.business.hourly_rate_minor = Math.round(parseFloat(value || 0) * 100); return;
      }
      if (group === 'business' && field === 'insurance_amount') {
        out.business.insurance_amount_minor = Math.round(parseFloat(value || 0) * 100); return;
      }
      if (group === 'location' && (field === 'latitude' || field === 'longitude')) {
        if (value !== '') out.location[field] = parseFloat(value);
        return;
      }
      if (group === 'location' && field === 'service_radius_km') {
        out.location[field] = parseFloat(value || 0); return;
      }
      out[group][field] = value;
    });
    // Compact services list (drop empties)
    out.services = out.services.filter(function (s) { return s && s.title; });
    return out;
  }

  show(current);
})();
</script>
</body>
</html>
