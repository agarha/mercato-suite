import { test, expect } from '@playwright/test';
import scenarios from './mvp-scenarios.json';

const baseURL = process.env.MERCATO_E2E_BASE_URL || 'http://localhost:8092';
const secret = process.env.MERCATO_TEST_API_SECRET || 'mercato-local-test-secret';

test.describe('Mercato top-30 MVP scenario catalog', () => {
  test('catalog contains exactly 30 uniquely identified MVP scenarios', async () => {
    expect(scenarios).toHaveLength(30);
    expect(new Set(scenarios.map((scenario) => scenario.id)).size).toBe(30);
  });

  for (const scenario of scenarios.filter((item) => item.url.startsWith('/?rest_route=/mercato/v1/health') || item.url === '/metrics')) {
    test(`${scenario.id} ${scenario.name}`, async ({ request }) => {
      const response = await request.get(`${baseURL}${scenario.url}`, {
        headers: { 'X-Mercato-Test-Secret': secret },
      });
      expect(response.status()).toBe(200);
      const body = await response.text();
      if (scenario.assertions.includes('body-ok')) {
        expect(body).toContain('ok');
      }
      if (scenario.assertions.includes('prometheus-counter')) {
        expect(body).toContain('mercato_outbox_published_total');
      }
    });
  }
});
