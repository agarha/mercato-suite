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

  function setStatus(message) {
    const node = root.querySelector('[data-status]');
    if (node) node.textContent = message;
  }

  async function renderAdmin() {
    root.innerHTML = `
      <div class="mercato-layout">
        <div class="mercato-header">
          <div>
            <h1 class="mercato-title">Mercato Operations</h1>
            <p class="mercato-subtitle">Tenant marketplace control, vendor onboarding, payouts, reporting, and delivery status.</p>
          </div>
          <div class="mercato-actions">
            <button class="mercato-button secondary" data-refresh>Refresh</button>
            <button class="mercato-button" data-payout>Schedule Payout</button>
          </div>
        </div>
        <div class="mercato-status" data-status></div>
        <div class="mercato-grid" data-metrics></div>
        <div class="mercato-grid">
          <div class="mercato-panel"><h2>Vendors</h2><div data-vendors></div></div>
          <div class="mercato-panel"><h2>Operations Health</h2><div data-health></div></div>
          <div class="mercato-panel"><h2>Send Test Email</h2>${emailForm()}</div>
        </div>
        <div class="mercato-panel"><h2>Vendor Performance</h2><div data-vendor-report></div></div>
      </div>`;

    root.querySelector('[data-refresh]').addEventListener('click', loadAdmin);
    root.querySelector('[data-payout]').addEventListener('click', schedulePayout);
    root.querySelector('[data-email-form]').addEventListener('submit', sendEmail);
    root.querySelector('[data-vendors]').addEventListener('click', updateVendorStatus);
    await loadAdmin();
  }

  function emailForm() {
    return `<form class="mercato-form" data-email-form>
      <label>Recipient <input name="recipient" type="email" value="ops@example.com" required></label>
      <label>Subject <input name="subject" value="Mercato notification" required></label>
      <label>Body <textarea name="body" rows="4" required>Marketplace notification test.</textarea></label>
      <button class="mercato-button" type="submit">Send</button>
    </form>`;
  }

  async function loadAdmin() {
    setStatus('Loading marketplace data...');
    const [dashboard, vendors, vendorReport, health] = await Promise.all([
      api('/reports/dashboard'),
      api('/vendors'),
      api('/reports/vendors'),
      api('/health/readiness'),
    ]);
    root.querySelector('[data-metrics]').innerHTML = [
      ['Net GMV', money(dashboard.net_gmv_minor)],
      ['Refunds', money(dashboard.refunded_minor)],
      ['Net Take', money(dashboard.net_take_minor)],
      ['AOV', money(dashboard.aov_minor)],
      ['Payouts', money(dashboard.payout_volume_minor)],
      ['Vendors', dashboard.vendor_count],
      ['Products', dashboard.product_count],
      ['Suborders', dashboard.suborder_count],
    ].map(([label, value]) => `<div class="mercato-panel mercato-metric"><span>${label}</span><strong>${value}</strong></div>`).join('');
    root.querySelector('[data-vendors]').innerHTML = vendorTable(vendors);
    root.querySelector('[data-health]').innerHTML = healthPanel(health);
    root.querySelector('[data-vendor-report]').innerHTML = table(['Vendor', 'Suborders', 'Net GMV', 'Refunds', 'Net Take', 'Net Vendor'], (vendorReport.vendors || []).map((v) => [
      v.vendor_id,
      v.suborder_count,
      money(v.net_gmv_minor),
      money(v.refunded_minor),
      money(v.net_take_minor),
      money(v.net_vendor_minor),
    ]));
    setStatus(`Updated ${new Date().toLocaleTimeString()}`);
  }

  function vendorTable(vendors) {
    if (!vendors.length) return '<p>No records yet.</p>';
    return `<table class="mercato-table"><thead><tr><th>ID</th><th>Business</th><th>Status</th><th>Stripe</th><th>Actions</th></tr></thead><tbody>${vendors.map((v) => {
      const id = esc(v.vendor_id);
      const status = esc(v.status);
      const actions = [
        `<button class="mercato-status-button" type="button" title="Approve" data-vendor-status="approved" data-vendor-id="${id}">Approve</button>`,
        `<button class="mercato-icon-button" type="button" title="Suspend" data-vendor-status="suspended" data-vendor-id="${id}">!</button>`,
      ].join('');
      return `<tr><td>${id}</td><td>${esc(v.business_name)}</td><td><span class="mercato-badge">${status}</span></td><td>${esc(v.stripe_account_id || '')}</td><td><div class="mercato-row-actions">${actions}</div></td></tr>`;
    }).join('')}</tbody></table>`;
  }

  function healthPanel(health) {
    const checks = health.checks || {};
    return `<div class="mercato-health">
      <div><span>Status</span><strong>${esc(health.status)}</strong></div>
      <div><span>Modules</span><strong>${esc(checks.modules && checks.modules.count)}</strong></div>
      <div><span>Outbox Pending</span><strong>${esc(checks.outbox && checks.outbox.pending_count)}</strong></div>
      <div><span>Outbox DLQ</span><strong>${esc(checks.outbox && checks.outbox.dlq_count)}</strong></div>
    </div>`;
  }

  async function updateVendorStatus(event) {
    const button = event.target.closest('[data-vendor-status]');
    if (!button) return;
    const status = button.dataset.vendorStatus;
    const vendorId = button.dataset.vendorId;
    setStatus(`Updating vendor ${vendorId}...`);
    await api(`/vendors/${vendorId}/status`, { method: 'POST', body: JSON.stringify({ status, reason: status === 'suspended' ? 'admin_action' : null }) });
    await loadAdmin();
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
  }

  async function renderVendor() {
    root.innerHTML = `
      <div class="mercato-layout">
        <div class="mercato-header">
          <div>
            <h1 class="mercato-title">Mercato Vendor Console</h1>
            <p class="mercato-subtitle">Register a storefront, connect payouts, publish products, and upload media.</p>
          </div>
          <button class="mercato-button secondary" data-refresh>Refresh</button>
        </div>
        <div class="mercato-status" data-status></div>
        <div class="mercato-grid">
          <div class="mercato-panel"><h2>Register Vendor</h2>${vendorForm()}</div>
          <div class="mercato-panel"><h2>Create Product</h2>${productForm()}</div>
          <div class="mercato-panel"><h2>Upload Media</h2>${mediaForm()}</div>
        </div>
        <div class="mercato-panel"><h2>Products</h2><div data-products></div></div>
      </div>`;
    root.querySelector('[data-refresh]').addEventListener('click', loadVendor);
    root.querySelector('[data-vendor-form]').addEventListener('submit', registerVendor);
    root.querySelector('[data-product-form]').addEventListener('submit', createProduct);
    root.querySelector('[data-media-form]').addEventListener('submit', uploadMedia);
    await loadVendor();
  }

  function vendorForm() {
    return `<form class="mercato-form" data-vendor-form>
      <label>Business Name <input name="business_name" required></label>
      <label>Store Slug <input name="store_slug" required></label>
      <button class="mercato-button" type="submit">Register</button>
    </form>`;
  }

  function productForm() {
    return `<form class="mercato-form" data-product-form>
      <label>Vendor ID <input name="vendor_id" type="number" required></label>
      <label>Title <input name="title" required></label>
      <label>SKU <input name="sku" required></label>
      <label>Price <input name="price_minor" type="number" value="2500" required></label>
      <label>Stock <input name="stock_quantity" type="number" value="10" required></label>
      <button class="mercato-button" type="submit">Create Product</button>
    </form>`;
  }

  function mediaForm() {
    return `<form class="mercato-form" data-media-form>
      <label>Owner Product ID <input name="owner_id" type="number" required></label>
      <label>File <input name="file" type="file" required></label>
      <button class="mercato-button" type="submit">Upload</button>
    </form>`;
  }

  async function loadVendor() {
    setStatus('Loading products...');
    const products = await api('/products');
    root.querySelector('[data-products]').innerHTML = table(['ID', 'Vendor', 'Title', 'Price', 'Stock', 'Status'], products.map((p) => [
      p.product_id,
      p.vendor_id,
      esc(p.title),
      money(p.price_minor),
      p.stock_quantity,
      esc(p.status),
    ]));
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
    if (!rows.length) return '<p>No records yet.</p>';
    return `<table class="mercato-table"><thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
  }

  (config.page === 'vendor' ? renderVendor : renderAdmin)().catch((error) => setStatus(error.message));
})();
