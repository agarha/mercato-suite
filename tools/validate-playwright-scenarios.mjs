import fs from 'node:fs';

const file = 'tests/playwright/mvp-scenarios.json';
const scenarios = JSON.parse(fs.readFileSync(file, 'utf8'));
const requiredAreas = new Set([
  'platform',
  'observability',
  'admin',
  'vendor',
  'payments',
  'compliance',
  'catalog',
  'media',
  'orders',
  'refunds',
  'payouts',
  'ledger',
  'notifications',
  'reports',
]);

if (!Array.isArray(scenarios) || scenarios.length !== 30) {
  throw new Error(`Expected exactly 30 Playwright MVP scenarios, found ${Array.isArray(scenarios) ? scenarios.length : 'invalid JSON'}.`);
}

const ids = new Set();
const areas = new Set();
for (const scenario of scenarios) {
  for (const key of ['id', 'name', 'url', 'assertions', 'area']) {
    if (!(key in scenario)) {
      throw new Error(`Scenario is missing ${key}: ${JSON.stringify(scenario)}`);
    }
  }
  if (ids.has(scenario.id)) {
    throw new Error(`Duplicate scenario id: ${scenario.id}`);
  }
  ids.add(scenario.id);
  areas.add(scenario.area);
  if (!/^PW-\d{3}$/.test(scenario.id)) {
    throw new Error(`Invalid scenario id: ${scenario.id}`);
  }
  if (!Array.isArray(scenario.assertions) || scenario.assertions.length === 0) {
    throw new Error(`Scenario ${scenario.id} must include assertions.`);
  }
}

for (const area of requiredAreas) {
  if (!areas.has(area)) {
    throw new Error(`Missing Playwright scenario area: ${area}`);
  }
}

console.log(JSON.stringify({
  status: 'passed',
  scenarios: scenarios.length,
  areas: [...areas].sort(),
}));
