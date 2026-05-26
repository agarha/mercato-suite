# Release and PR Workflow

Status: repo process documented; GitHub branch protection requires repository admin action.

## Branches

- `main`: protected release branch
- `codex/e2e-developed`: active implementation branch
- `codex/*`: feature and automation branches

## Required Checks

- PHPStan
- PHPUnit
- manifest validation
- contract validation
- JS asset validation
- Playwright top-30 catalog validation
- security gate
- Docker image build
- release artifact build

## Pull Requests

1. One milestone per PR where practical.
2. Evidence links must include tests, migration impact, and runbook updates.
3. MVP/P1 work requires explicit acceptance criteria in the PR body.
4. Merge only after required checks pass.

## Release Tags

Use semantic plugin tags:

```powershell
git tag -a v0.1.0 -m "Mercato MVP release candidate 0.1.0"
git push origin v0.1.0
```

## External Admin Action Required

Configure branch protection on `main`:

- require pull request before merging
- require status checks
- require linear history
- block force pushes
- restrict who can dismiss reviews
