# Mercato MVP Gate Status

Last updated: 2026-05-22

| Gate | Status | Evidence | Next action |
|---|---|---|---|
| MVP Cut approved | Partial | `docs_v2/00_mvp_cut/MVP_Cut.md` | Add explicit approval record. |
| All P1 gaps closed or formally deferred | Partial | `docs_v2/deliverables/Gap_Matrix.md`, `GITHUB_ISSUE_BACKLOG.md` | Sync GitHub issues after permissions are fixed; close or block each P1. |
| Core plugin/module structure implemented | Done | `apps/wordpress/wp-content/plugins/mercato-suite/modules` | Maintain manifest validation in CI. |
| Vendor onboarding works end-to-end | Done | `tools/run-e2e-smoke.ps1` | Add Playwright browser scenario. |
| Product listing works end-to-end | Done | `tools/run-e2e-smoke.ps1`, admin/vendor JS | Add moderation/category coverage. |
| Multi-vendor checkout works end-to-end | Partial | E2E smoke covers order split path with discount, shipping, tax, and tracking allocations | Add true multi-vendor cart/browser checkout and Woo conflict matrix. |
| Suborders created correctly | Done | `mercato-orders/src/Splitter.php`, E2E DB summary | Add integration tests around edge cases. |
| Commissions calculated correctly | Partial | `mercato-commissions/src/Calculator.php`, E2E, balanced ledger entries | Add category/product/tier rule tests. |
| Refund reversals work | Done | E2E smoke verifies refunds and commission reversals | Add Woo native refund integration test. |
| Stripe payout sandbox works | Done | E2E smoke executes test-mode transfers | Add failure/retry scenario. |
| Vendor dashboard works | Partial | WP admin vendor shell | Build richer SPA/browser tests. |
| Tenant admin dashboard works | Partial | WP admin operations shell | Add browser/accessibility tests. |
| Basic reports work | Done | E2E smoke verifies dashboard/export/reconciliation/trial balance | Add contract tests. |
| RBAC tested | Partial | REST negative security smoke exists | Add route-level capability tests. |
| Tenant isolation tested | Partial | Tenant-scoped code exists | Add automated cross-tenant tests. |
| API contract tests pass | Partial | `tools/validate-contracts.py` validates implemented MVP route/event overlay | Sync all MVP routes/events into docs OpenAPI/AsyncAPI and add schema-level tests. |
| Top MVP E2E tests pass | Partial | One broad E2E smoke passes, including payment/refund/payout/outbox/allocation/tracking | Add Playwright top workflow suite. |
| k6 baseline test executed | Missing | No k6 scripts yet | Add k6 baseline and run locally/staging. |
| Security scans pass with no critical/high unresolved | Missing | Runtime rate limits exist, but CI lacks SAST/SCA/IaC scan gates | Add workflow steps and baseline report. |
| Backup/restore tested | Missing | No restore drill proof | Add local DB backup/restore drill and cloud runbook. |
| DR partial drill completed | Blocked | Requires staging/cloud environment | Create GitHub issue after permissions fixed; document runbook. |
| UAT sign-off completed | Blocked | Requires beta tenant/user signoff | Create issue and UAT scripts. |
| Release notes prepared | Partial | `CHANGELOG.md`, release artifact | Add tagged release process. |

Gate result: **Not ready for MVP launch**. The local technical smoke path is healthy, but launch gates around tests, security, cloud operations, governance, and signoff remain open.
