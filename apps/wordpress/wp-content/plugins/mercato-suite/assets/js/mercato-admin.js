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
          <div class="mercato-panel"><h2>Send Test Email</h2>${emailForm()}</div>
        </div>
        <div class="mercato-panel"><h2>Vendor Performance</h2><div data-vendor-report></div></div>
      </div>`;

    root.querySelector('[data-refresh]').addEventListener('click', loadAdmin);
    root.querySelector('[data-payout]').addEventListener('click', schedulePayout);
    root.querySelector('[data-email-form]').addEventListener('submit', sendEmail);
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
    const [dashboard, vendors, vendorReport] = await Promise.all([
      api('/reports/dashboard'),
      api('/vendors'),
      api('/reports/vendors'),
    ]);
    root.querySelector('[data-metrics]').innerHTML = [
      ['GMV', money(dashboard.gmv_minor)],
      ['Take', money(dashboard.take_minor)],
      ['Vendors', dashboard.vendor_count],
      ['Products', dashboard.product_count],
      ['Suborders', dashboard.suborder_count],
    ].map(([label, value]) => `<div class="mercato-panel mercato-metric"><span>${label}</span><strong>${value}</strong></div>`).join('');
    root.querySelector('[data-vendors]').innerHTML = table(['ID', 'Business', 'Status', 'Stripe'], vendors.map((v) => [
      v.vendor_id,
      esc(v.business_name),
      esc(v.status),
      esc(v.stripe_account_id || ''),
    ]));
    root.querySelector('[data-vendor-report]').innerHTML = table(['Vendor', 'Suborders', 'GMV', 'Take', 'Vendor Net'], (vendorReport.vendors || []).map((v) => [
      v.vendor_id,
      v.suborder_count,
      money(v.gmv_minor),
      money(v.take_minor),
      money(v.vendor_net_minor),
    ]));
    setStatus(`Updated ${new Date().toLocaleTimeString()}`);
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
