import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

export const options = {
  scenarios: {
    smoke_baseline: {
      executor: 'constant-vus',
      vus: Number(__ENV.MERCATO_K6_VUS || 1),
      duration: __ENV.MERCATO_K6_DURATION || '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: [`p(95)<${Number(__ENV.MERCATO_K6_HTTP_P95_MS || 6000)}`],
    mercato_readiness_ok: ['rate>0.99'],
    mercato_metrics_latency: [`p(95)<${Number(__ENV.MERCATO_K6_METRICS_P95_MS || 6000)}`],
  },
};

const baseUrl = __ENV.MERCATO_BASE_URL || 'http://host.docker.internal:8092';
const testSecret = __ENV.MERCATO_TEST_API_SECRET || 'mercato-local-test-secret';
const headers = { 'X-Mercato-Test-Secret': testSecret };

export const readinessOk = new Rate('mercato_readiness_ok');
export const metricsLatency = new Trend('mercato_metrics_latency');

export default function () {
  const live = http.get(`${baseUrl}/?rest_route=/mercato/v1/health/live`);
  check(live, {
    'live status is 200': (r) => r.status === 200,
    'live payload is ok': (r) => r.body && r.body.includes('"ok"'),
  });

  const readiness = http.get(`${baseUrl}/?rest_route=/mercato/v1/health/readiness`, { headers });
  const readinessPassed = check(readiness, {
    'readiness status is 200': (r) => r.status === 200,
    'readiness payload is ok': (r) => r.body && r.body.includes('"ok"'),
  });
  readinessOk.add(readinessPassed);

  const products = http.get(`${baseUrl}/?rest_route=/mercato/v1/products`, { headers });
  check(products, {
    'products list is available': (r) => r.status === 200,
  });

  const metrics = http.get(`${baseUrl}/metrics`, { headers });
  metricsLatency.add(metrics.timings.duration);
  check(metrics, {
    'metrics status is 200': (r) => r.status === 200,
    'metrics exposes outbox counter': (r) => r.body && r.body.includes('mercato_outbox_published_total'),
  });

  sleep(1);
}
