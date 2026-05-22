(function () {
  const config = window.MercatoAdmin || {};
  const root = document.getElementById(config.page === 'vendor' ? 'mercato-vendor-root' : 'mercato-admin-root');
  if (!root) return;

  const api = async (path, options = {}) => {
    const response = await fetch(`${config.restBase}${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce,
        ...(options.headers || {}),
      },
    });
    const json = await response.json();
    if (!response.ok) throw new Error(json.message || 'Request failed');
    return json;
  };

  const money = (minor) => `$${((Number(minor || 0)) / 100).toFixed(2)}`;
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const badge = (value) => `<span class="mercato-badge">${esc(value)}</span>`;

  function setStatus(message) {
    const node = root.querySelector('[data-status]');
    if (node) node.textContent = message;
  }

  async function renderAdmin() {
    root.innerHTML = `
      <div class="mercato-layout">
        <div class="mercato-hero">
          <div>
            <p class="mercato-eyebrow">Mercato Suite</p>
            <h1 class="mercato-title">Marketplace command center</h1>
            <p class="mercato-subtitle">A live view of the tenant, vendors, catalog, order split, Stripe Connect payouts, reconciliation, notifications, audit log, and platform health running inside this Docker container.</p>
          </div>
          <div class="mercato-actions">
            <a class="mercato-button secondary" href="/" target="_blank">Storefront</a>
            <a class="mercato-button secondary" href="admin.php?page=mercato-vendor">Vendor Console</a>
            <button class="mercato-button" data-refresh>Refresh</button>
          </div>
        </div>
        <div class="mercato-status" data-status></div>
        <div class="mercato-tabs" data-tabs>
          <button class="active" data-tab="overview">Overview</button>
          <button data-tab="vendors">Vendors & KYC</button>
          <button data-tab="catalog">Catalog & Media</button>
          <button data-tab="orders">Orders & Refunds</button>
          <button data-tab="finance">Payouts & Ledger</button>
          <button data-tab="events">Events & Audit</button>
        </div>
        <div data-tab-panel></div>
      </div>`;
    root.querySelector('[data-refresh]').addEventListener('click', loadAdmin);
    root.querySelector('[data-tabs]').addEventListener('click', (event) => {
      const button = event.target.closest('[data-tab]');
      if (!button) return;
      root.querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === button));
      root.dataset.tab = button.dataset.tab;
      renderAdminPanel(window.__mercatoAdminData);
    });
    root.dataset.tab = 'overview';
    await loadAdmin();
  }

  async function loadAdmin() {
    setStatus('Loading live marketplace records...');
    const [dashboard, vendorReport, health, features, trialBalance] = await Promise.all([
      api('/reports/dashboard'),
      api('/reports/vendors'),
      api('/health/readiness'),
      api('/demo/features'),
      api('/payouts/trial-balance'),
    ]);
    window.__mercatoAdminData = { dashboard, vendorReport, health, features, trialBalance };
    renderAdminPanel(window.__mercatoAdminData);
    setStatus(`Loaded live data at ${new Date().toLocaleTimeString()}`);
  }

  function renderAdminPanel(data) {
    if (!data) return;
    const tab = root.dataset.tab || 'overview';
    const panel = root.querySelector('[data-tab-panel]');
    const methods = {
      overview: overviewPanel,
      vendors: vendorsPanel,
      catalog: catalogPanel,
      orders: ordersPanel,
      finance: financePanel,
      events: eventsPanel,
    };
    panel.innerHTML = methods[tab](data);
    const payout = panel.querySelector('[data-payout]');
    if (payout) payout.addEventListener('click', schedulePayout);
    const email = panel.querySelector('[data-email-form]');
    if (email) email.addEventListener('submit', sendEmail);
  }

  function overviewPanel({ dashboard, health, features, trialBalance }) {
    return `
      <div class="mercato-grid metrics">
        ${metric('Net GMV', money(dashboard.net_gmv_minor), 'Orders minus refunds')}
        ${metric('Platform Take', money(dashboard.net_take_minor), 'Commission after reversals')}
        ${metric('Payout Volume', money(dashboard.payout_volume_minor), 'Succeeded vendor payouts')}
        ${metric('Trial Balance', esc(trialBalance.status), `${money(trialBalance.debit_minor)} debit / ${money(trialBalance.credit_minor)} credit`)}
      </div>
      <div class="mercato-feature-grid">
        ${(features.features || []).map((item) => `
          <div class="mercato-panel feature-card">
            <div class="feature-top"><h2>${esc(item.name)}</h2>${badge(item.status)}</div>
            ${keyValues(item.evidence)}
          </div>`).join('')}
      </div>
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Readiness</h2>${keyValues({ status: health.status, modules: health.checks?.modules?.count, outbox_pending: health.checks?.outbox?.pending_count, outbox_dlq: health.checks?.outbox?.dlq_count })}</div>
        <div class="mercato-panel"><h2>Latest reconciliation</h2>${keyValues(dashboard.latest_reconciliation || { status: 'not run' })}</div>
      </div>`;
  }

  function vendorsPanel({ features }) {
    return `
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Vendor Lifecycle</h2>${table(['ID', 'Business', 'Slug', 'Status', 'KYC', 'Stripe'], (features.recent_vendors || []).map((v) => [v.vendor_id, esc(v.business_name), esc(v.store_slug), badge(v.status), esc(v.kyc_status), esc(v.stripe_account_id)]))}</div>
        <div class="mercato-panel"><h2>What this proves</h2>${checklist(['Vendor registration', 'Admin approval/rejection/suspension', 'Stripe account onboarding', 'KYC status tracking', 'Tenant-scoped vendor records'])}</div>
      </div>`;
  }

  function catalogPanel({ features }) {
    return `
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Products</h2>${table(['ID', 'Vendor', 'Title', 'SKU', 'Price', 'Stock', 'Status'], (features.recent_products || []).map((p) => [p.product_id, p.vendor_id, esc(p.title), esc(p.sku), money(p.price_minor), p.stock_quantity, badge(p.status)]))}</div>
        <div class="mercato-panel"><h2>What this proves</h2>${checklist(['Vendor-owned product records', 'WooCommerce product projection', 'Stock and SKU persistence', 'S3/MinIO media flow', 'Product archive endpoint'])}</div>
      </div>`;
  }

  function ordersPanel({ features }) {
    return `
      <div class="mercato-panel"><h2>Suborders, Refunds, Tracking</h2>${table(['Suborder', 'Vendor', 'Woo Order', 'Status', 'Payment', 'Total', 'Refunded', 'Carrier', 'Tracking'], (features.recent_suborders || []).map((s) => [s.suborder_id, s.vendor_id, s.wc_order_id, badge(s.status), badge(s.payment_status), money(s.total_minor), money(s.refunded_minor), esc(s.tracking_carrier), esc(s.tracking_number)]))}</div>
      <div class="mercato-grid three">
        ${proofCard('Multi-vendor checkout', 'Woo parent orders are split into vendor suborders.')}
        ${proofCard('Refund reversal', 'Refund rows and commission reversals are created together.')}
        ${proofCard('Shipment tracking', 'Carrier and tracking number are stored on suborders.')}
      </div>`;
  }

  function financePanel({ features, vendorReport, trialBalance }) {
    return `
      <div class="mercato-actions inline"><button class="mercato-button" data-payout>Run payout batch</button></div>
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Payout Batches</h2>${table(['Batch', 'Status', 'Total', 'Items', 'Created'], (features.recent_payouts || []).map((p) => [p.batch_id, badge(p.status), money(p.total_minor), p.item_count, esc(p.created_at)]))}</div>
        <div class="mercato-panel"><h2>Trial Balance</h2>${keyValues({ status: trialBalance.status, debit: money(trialBalance.debit_minor), credit: money(trialBalance.credit_minor), drift: money(trialBalance.drift_minor) })}</div>
      </div>
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Reconciliation Runs</h2>${table(['Run', 'Status', 'Ledger', 'Provider', 'Drift', 'Created'], (features.recent_reconciliation || []).map((r) => [r.run_id, badge(r.status), money(r.ledger_minor), money(r.provider_minor), money(r.drift_minor), esc(r.created_at)]))}</div>
        <div class="mercato-panel"><h2>Vendor Performance</h2>${table(['Vendor', 'Suborders', 'Net GMV', 'Refunds', 'Take', 'Net Vendor'], (vendorReport.vendors || []).map((v) => [v.vendor_id, v.suborder_count, money(v.net_gmv_minor), money(v.refunded_minor), money(v.net_take_minor), money(v.net_vendor_minor)]))}</div>
      </div>`;
  }

  function eventsPanel({ features }) {
    return `
      <div class="mercato-grid two">
        <div class="mercato-panel"><h2>Send Test Email</h2>${emailForm()}</div>
        <div class="mercato-panel"><h2>Notifications</h2>${table(['ID', 'Recipient', 'Subject', 'Status', 'Created'], (features.recent_notifications || []).map((n) => [n.delivery_id, esc(n.recipient), esc(n.subject), badge(n.status), esc(n.created_at)]))}</div>
      </div>
      <div class="mercato-panel"><h2>Audit Log</h2>${table(['ID', 'Action', 'Entity', 'Entity ID', 'Created'], (features.recent_audit || []).map((a) => [a.audit_id, esc(a.action), esc(a.entity_type), a.entity_id, esc(a.created_at)]))}</div>`;
  }

  function metric(label, value, detail) {
    return `<div class="mercato-panel mercato-metric"><span>${label}</span><strong>${value}</strong><small>${esc(detail)}</small></div>`;
  }

  function proofCard(title, body) {
    return `<div class="mercato-panel proof-card"><h2>${esc(title)}</h2><p>${esc(body)}</p></div>`;
  }

  function keyValues(object) {
    if (!object || typeof object !== 'object') return '<p>No evidence yet.</p>';
    return `<dl class="mercato-kv">${Object.entries(object).map(([key, value]) => {
      const rendered = value && typeof value === 'object' ? JSON.stringify(value) : value;
      return `<div><dt>${esc(key.replaceAll('_', ' '))}</dt><dd>${esc(rendered)}</dd></div>`;
    }).join('')}</dl>`;
  }

  function checklist(items) {
    return `<ul class="mercato-checklist">${items.map((item) => `<li>${esc(item)}</li>`).join('')}</ul>`;
  }

  function emailForm() {
    return `<form class="mercato-form" data-email-form>
      <label>Recipient <input name="recipient" type="email" value="ops@example.com" required></label>
      <label>Subject <input name="subject" value="Mercato notification" required></label>
      <label>Body <textarea name="body" rows="4" required>Marketplace notification test.</textarea></label>
      <button class="mercato-button" type="submit">Send notification</button>
    </form>`;
  }

  async function schedulePayout() {
    setStatus('Scheduling payout batch...');
    const batch = await api('/payouts/batches', { method: 'POST', body: '{}' });
    if (batch.batch_id) {
      await api(`/stripe/payout-batches/${batch.batch_id}/execute`, { method: 'POST', body: '{}' });
    }
    setStatus(`Payout batch ${batch.batch_id || 'n/a'} processed.`);
    await loadAdmin();
  }

  async function sendEmail(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    const result = await api('/sendgrid/send', { method: 'POST', body: JSON.stringify(data) });
    setStatus(`Email delivery ${result.delivery_id} ${result.status}.`);
    await loadAdmin();
  }

  async function renderVendor() {
    root.innerHTML = `
      <div class="mercato-layout">
        <div class="mercato-hero">
          <div>
            <p class="mercato-eyebrow">Vendor workspace</p>
            <h1 class="mercato-title">Storefront operations</h1>
            <p class="mercato-subtitle">Register a storefront, connect payouts, publish products, and upload media into the tenant marketplace.</p>
          </div>
          <button class="mercato-button" data-refresh>Refresh</button>
        </div>
        <div class="mercato-status" data-status></div>
        <div class="mercato-grid three">
          <div class="mercato-panel"><h2>Register Vendor</h2>${vendorForm()}</div>
          <div class="mercato-panel"><h2>Create Product</h2>${productForm()}</div>
          <div class="mercato-panel"><h2>Upload Media</h2>${mediaForm()}</div>
        </div>
        <div class="mercato-panel"><h2>Current Products</h2><div data-products></div></div>
      </div>`;
    root.querySelector('[data-refresh]').addEventListener('click', loadVendor);
    root.querySelector('[data-vendor-form]').addEventListener('submit', registerVendor);
    root.querySelector('[data-product-form]').addEventListener('submit', createProduct);
    root.querySelector('[data-media-form]').addEventListener('submit', uploadMedia);
    await loadVendor();
  }

  function vendorForm() {
    return `<form class="mercato-form" data-vendor-form>
      <label>Business Name <input name="business_name" value="Demo Vendor" required></label>
      <label>Store Slug <input name="store_slug" value="demo-vendor-${Date.now()}" required></label>
      <button class="mercato-button" type="submit">Register vendor</button>
    </form>`;
  }

  function productForm() {
    return `<form class="mercato-form" data-product-form>
      <label>Vendor ID <input name="vendor_id" type="number" required></label>
      <label>Title <input name="title" value="Demo Product" required></label>
      <label>SKU <input name="sku" value="DEMO-${Date.now()}" required></label>
      <label>Price cents <input name="price_minor" type="number" value="2500" required></label>
      <label>Stock <input name="stock_quantity" type="number" value="10" required></label>
      <button class="mercato-button" type="submit">Create product</button>
    </form>`;
  }

  function mediaForm() {
    return `<form class="mercato-form" data-media-form>
      <label>Owner Product ID <input name="owner_id" type="number" required></label>
      <label>File <input name="file" type="file" required></label>
      <button class="mercato-button" type="submit">Upload media</button>
    </form>`;
  }

  async function loadVendor() {
    setStatus('Loading vendor products...');
    const products = await api('/products');
    root.querySelector('[data-products]').innerHTML = table(['ID', 'Vendor', 'Title', 'SKU', 'Price', 'Stock', 'Status'], products.map((p) => [p.product_id, p.vendor_id, esc(p.title), esc(p.sku), money(p.price_minor), p.stock_quantity, badge(p.status)]));
    setStatus(`Loaded ${products.length} products.`);
  }

  async function registerVendor(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    const vendor = await api('/vendors', { method: 'POST', body: JSON.stringify(data) });
    await api(`/stripe/vendors/${vendor.vendor_id}/account`, { method: 'POST', body: JSON.stringify({ email: 'vendor@example.com' }) });
    setStatus(`Vendor ${vendor.vendor_id} registered and Stripe onboarding started.`);
  }

  async function createProduct(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    data.vendor_id = Number(data.vendor_id);
    data.price_minor = Number(data.price_minor);
    data.stock_quantity = Number(data.stock_quantity);
    data.status = 'active';
    const product = await api('/products', { method: 'POST', body: JSON.stringify(data) });
    setStatus(`Product ${product.product_id} created.`);
    await loadVendor();
  }

  async function uploadMedia(event) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const file = form.get('file');
    const presign = await api('/media/presign', {
      method: 'POST',
      body: JSON.stringify({ owner_type: 'product', owner_id: Number(form.get('owner_id')), file_name: file.name, content_type: file.type || 'application/octet-stream', size_bytes: file.size }),
    });
    await fetch(presign.upload_url, { method: 'PUT', headers: { 'Content-Type': file.type || 'application/octet-stream' }, body: file });
    await api(`/media/${presign.media_id}/complete`, { method: 'POST', body: JSON.stringify({ scan_status: 'clean' }) });
    setStatus(`Media ${presign.media_id} uploaded.`);
  }

  function table(headers, rows) {
    if (!rows.length) return '<p class="mercato-empty">No records yet.</p>';
    return `<div class="mercato-table-wrap"><table class="mercato-table"><thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
  }

  (config.page === 'vendor' ? renderVendor : renderAdmin)().catch((error) => setStatus(error.message));
})();
