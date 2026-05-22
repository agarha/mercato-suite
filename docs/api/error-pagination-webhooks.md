# API Error, Pagination, and Webhook Hardening

Status: MVP contract supplement

## Error Shape

Mercato REST endpoints should converge on RFC 7807-compatible problem details:

```json
{
  "type": "https://mercato.local/problems/validation-error",
  "title": "Validation error",
  "status": 400,
  "detail": "vendor_id is required",
  "instance": "/mercato/v1/products"
}
```

Existing WordPress `WP_Error` responses remain compatible at runtime, but new endpoints should include the problem fields in the error data where practical.

## Cursor Pagination

List endpoints should accept:

- `limit`, default `50`, max `100`
- `cursor`, opaque base64 cursor

Responses should include:

- `data`
- `next_cursor`
- `has_more`

## Outbound Webhook HMAC

Tenant outbound webhooks must sign payloads:

```text
X-Mercato-Signature: hmac-sha256=<hex digest>
X-Mercato-Timestamp: <unix timestamp>
```

Signature input:

```text
<timestamp>.<raw request body>
```

Replay window: 5 minutes.

Current MVP status: inbound webhook dedup and rate-limit controls exist; tenant outbound webhooks remain a P1/P2 implementation gap unless required for launch tenant integrations.
