# Mercato Kubernetes Deployment

The MVP deployment path is Helm-first:

```powershell
helm upgrade --install mercato-suite infrastructure/helm/mercato-suite `
  --namespace mercato --create-namespace `
  --set wordpress.image.tag=0.1.0 `
  --set outboxRelay.image.tag=0.1.0
```

The chart includes:

- WordPress marketplace runtime with liveness/readiness probes.
- Static Go outbox relay deployment.
- Pre-install/pre-upgrade migration job.
- WP cron replacement CronJob.
- Prometheus ServiceMonitor scraping `/metrics`.

Secrets are represented as chart keys for local/staging installs. Production should inject them from AWS Secrets Manager or External Secrets and set `wordpress.secretRef` to the managed secret name.
