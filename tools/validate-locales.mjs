import fs from 'node:fs';

const dir = 'apps/wordpress/wp-content/plugins/mercato-suite/languages';
const baseline = JSON.parse(fs.readFileSync(`${dir}/mercato-suite-en_US.json`, 'utf8'));
const baselineKeys = Object.keys(baseline).sort();
const files = fs.readdirSync(dir).filter((file) => file.endsWith('.json'));

for (const file of files) {
  const data = JSON.parse(fs.readFileSync(`${dir}/${file}`, 'utf8'));
  const keys = Object.keys(data).sort();
  const missing = baselineKeys.filter((key) => !keys.includes(key));
  const extra = keys.filter((key) => !baselineKeys.includes(key));
  if (missing.length || extra.length) {
    throw new Error(`${file} locale key mismatch. missing=${missing.join(',')} extra=${extra.join(',')}`);
  }
}

console.log(JSON.stringify({
  status: 'passed',
  files: files.length,
  keys: baselineKeys.length,
}));
