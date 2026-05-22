# Mercato Plugin SDK Contract

Status: MVP draft published in repo

Every module must provide `module.json` and a service provider class.

## Manifest

Required fields:

- `slug`
- `namespace`
- `version`
- `sdk_version`
- `requires`
- `capabilities`
- `tables`
- `tier`
- `feature_flag`
- `provides_events`
- `consumes_events`

Validation:

```powershell
python tools\validate-manifests.py
```

## Service Provider

Providers extend `Mercato\Core\ServiceProvider` and implement:

- `register()` for container bindings
- `boot()` for hooks, routes, scheduled jobs, and subscribers

## Events

Events use the canonical taxonomy:

```text
mercato.<domain>.<event>.v1
```

Outbox publication must use `Mercato\Core\Events\Outbox`.

## REST

Routes register under `mercato/v1`. Sensitive routes must use `Mercato\Core\Rest\Permissions` and rate-limit buckets defined in `config/rate-limits.json`.

## CI Evidence

- Manifest validation
- Contract validation
- PHPUnit registry tests
- E2E smoke where the module participates in MVP flows
