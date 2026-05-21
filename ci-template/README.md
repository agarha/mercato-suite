# CI Workflow (manual installation)

This directory contains the GitHub Actions CI workflow that could not be pushed by
the deployment PAT because the token lacks the `workflow` scope.

To install it:

1. Move `ci.yml.workflow` → `.github/workflows/ci.yml` in your local clone.
2. Either:
   - **Option A (preferred):** commit + push using a PAT with `workflow` scope, OR
   - **Option B:** create the file via GitHub web UI ("Actions" tab → "set up a workflow").
3. Delete this `ci-template/` directory.

The workflow runs four jobs on push to main + PRs:
- `php`: matrix PHP 8.2/8.3 + PHPStan + PHPUnit
- `go`: Go 1.22 vet + build outbox-relay
- `module-manifests`: runs `tools/validate-manifests.py`
- `docker-build`: smoke-builds WordPress and outbox-relay images

Once installed, branch protection on `main` should require all four checks.
