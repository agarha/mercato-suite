import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const adminJs = path.join(root, 'apps/wordpress/wp-content/plugins/mercato-suite/assets/js/mercato-admin.js');
const adminCss = path.join(root, 'apps/wordpress/wp-content/plugins/mercato-suite/assets/css/mercato-admin.css');

const js = fs.readFileSync(adminJs, 'utf8');
const css = fs.readFileSync(adminCss, 'utf8');

new Function(js);

const requiredRoutes = [
  '/reports/dashboard',
  '/vendors',
  '/reports/vendors',
  '/health/readiness',
  '/payouts/batches',
  '/sendgrid/send',
  '/products',
  '/media/presign',
];

for (const route of requiredRoutes) {
  if (!js.includes(route)) {
    throw new Error(`Admin asset missing route ${route}`);
  }
}

const selectors = ['mercato-admin-root', 'mercato-vendor-root', 'mercato-layout', 'mercato-panel', 'mercato-table'];
for (const selector of selectors) {
  if (!js.includes(selector) && !css.includes(selector)) {
    throw new Error(`Admin assets missing selector ${selector}`);
  }
}

const openBraces = (css.match(/{/g) || []).length;
const closeBraces = (css.match(/}/g) || []).length;
if (openBraces !== closeBraces) {
  throw new Error(`CSS brace mismatch: ${openBraces} opening, ${closeBraces} closing`);
}

console.log(JSON.stringify({
  admin_js_bytes: js.length,
  admin_css_bytes: css.length,
  routes_checked: requiredRoutes.length,
  selectors_checked: selectors.length,
}));
