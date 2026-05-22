# Mercato Real-Work Audit Status

Last updated: 2026-05-22
Branch: `codex/e2e-developed`

This file tracks evidence against the Master Real-Work Audit Checklist. Status values are `Done`, `Partial`, `Missing`, `Blocked`, or `Deferred`.

## Scorecard

| Area | Weight | Score | Status | Evidence | Next action |
|---|---:|---:|---|---|---|
| MVP scope locked | 10 | 8 | Partial | `C:\Nex Repository\Mercato\docs_v2\00_mvp_cut\MVP_Cut.md`, `C:\Nex Repository\Mercato\docs_v2\deliverables\Implementation_Backlog.md` | Record formal approval/signoff. |
| Architecture implemented | 10 | 8 | Partial | `apps/wordpress/wp-content/plugins/mercato-suite/modules`, `tools/validate-manifests.py`, `services/outbox-relay` | Publish SDK docs/package and complete search/licensing/caching. |
| WooCommerce/HPOS integration | 15 | 11 | Partial | `mercato-core/src/WooCommerce/HookAdapter.php`, `mercato-orders/src/Splitter.php`, E2E smoke validates discount, shipping, tax, tracking | Complete coupon edge cases, buyer order views, and conflict tests. |
| Database/migrations | 10 | 8 | Partial | `modules/*/migrations`, `mercato-core/src/DB/Migrator.php`, `mercato-payouts/migrations/0003_accounting_ledger.sql` | Add rollback plan, partition job, DB grants. |
| Vendor/product/order flows | 15 | 10 | Partial | `tools/run-e2e-smoke.ps1`, admin assets, MVP REST modules | Complete rejection notification, onboarding checklist, moderation, category/attributes. |
| Commissions/payouts/ledger | 15 | 11 | Partial | `mercato-commissions`, `mercato-payouts`, `mercato-stripe-connect`, E2E reconciliation and trial balance | Add richer commission rule tests and payout failure workflow. |
| API/events | 7 | 6 | Partial | `docs_v2/07_openapi`, `packages/contracts/mercato-mvp-contract.json`, `tools/validate-contracts.py`, REST providers, outbox relay | Update docs contracts for all MVP routes/events; add rate limits, RFC 7807 errors, outbound webhooks. |
| Security/RBAC/tenant isolation | 8 | 4 | Partial | `mercato-core/src/RBAC`, `Rest/Permissions.php`, audit log | Add security tests, CSP, DSAR workflow, MFA/API key hashing where applicable. |
| QA/E2E/performance | 7 | 3 | Partial | PHPUnit, JS validation, E2E smoke, deployment validation | Add Playwright top workflows, k6, axe, contract tests, SAST/SCA/IaC scans. |
| DevOps/deployment/monitoring | 3 | 2 | Partial | Docker Compose, Helm chart, `/metrics`, preflight, release build | Add Terraform, backup/restore, DR drill, dashboards, alerts. |
| **Total** | **100** | **68** | **Not MVP-launch ready** | Full local verification passed after latest milestones | Close P1 gaps or formally block/defer with issue ownership. |

## Checklist Area Summary

| Area | Status | Evidence | Blockers / next actions |
|---|---|---|---|
| A. Repository & Governance | Partial | Docs repo and implementation repo exist; README/changelog/CI exist. GitHub issue creation was attempted and blocked with `403 Resource not accessible by integration`. | Enable GitHub App issue write permission or PAT; create issues from `GITHUB_ISSUE_BACKLOG.md`; configure branch protection/PR approvals/release tags. |
| B. MVP Scope Validation | Partial | MVP Cut, backlog, readiness scorecard, E2E demo script exist. | Formal MVP approval, owner/due-date assignments, and exit-gate signoff missing. |
| C. Architecture | Partial | 29 modules, manifests, DI, service provider contract, outbox, relay, RBAC, tenant resolver, audit, idempotency. | SDK publication, search adapter, generic inbox, Redis caching, licensing enforcement incomplete. |
| D. WooCommerce / HPOS | Partial | HPOS guard, hook adapter, suborders, split logic, refund reversal, discount allocation, tax allocation, shipping allocation, shipment tracking. | Coupon edge cases, buyer order page, and conflict tests incomplete. |
| E. Database & Migrations | Partial | Migration runner, MVP tables, and accounting ledger table exist. | Rollbacks, partition maintenance, DB role grants, online schema change process missing. |
| F. Product / Vendor Lifecycle | Partial | Vendor signup, approval/suspension, KYC, dashboard shell. | Rejection notifications, staff roles, onboarding checklist, profile settings depth missing. |
| G. Product & Catalog | Partial | Product create/list/archive, Woo projection, media upload. | Category/attributes, variable products, importer, moderation queue, search indexing incomplete. |
| H. Order / Checkout / Refund | Partial | E2E creates Woo parent order, allocated suborders, PaymentIntent, tracking, refund reversal. | Buyer account page, refund request/approval UI, chargeback/dispute workflow missing. |
| I. Commission & Payout | Partial | Commission calculation, reversal, payout batch, Stripe sandbox transfers, reconciliation, balanced trial balance. | Tier/category/product rule coverage and payout failure workflow incomplete. |
| J. API & Webhook | Partial | REST routes, permissions, idempotency, Stripe/KYC/SendGrid webhook paths, MVP route/event contract validation. | Docs contract sync, pagination, rate limits, RFC 7807, outbound webhook HMAC implementation missing. |
| K. UX / Frontend | Partial | WordPress admin/vendor UI shell and asset validation. | Real SPA workspaces, accessibility tests, i18n/microcopy, buyer storefront missing. |
| L. Security & Compliance | Partial | RBAC foundation, tenant-scoped queries, audit log, upload controls. | MFA, DSAR, CSP, SIEM, pentest, SOC 2, API key hashing evidence missing. |
| M. QA / Testing | Partial | PHPUnit, manifest validation, JS asset validation, full E2E smoke. | Playwright, k6, axe, SAST/SCA/IaC, UAT scripts missing. |
| N. DevOps / Infrastructure | Partial | Docker, CI, Helm, metrics, release artifact. | Terraform, real cloud deploy, backups, DR, alerts, cost tagging missing. |
| O. AI Copilot | Deferred | AI module/migration exists; MVP Cut defers AI. | Create post-MVP issues for AI service, provider gateway, guardrails, vector store, evals, zero-retention contracts. |

## Verification Evidence

Latest completed verification before this audit:

- `tools/run-tests.ps1` with `MERCATO_RUN_E2E=1`
- `tools/deploy-preflight.ps1`
- `tools/build-release.ps1`
- `npm test`
- `tools/validate-deployment-assets.ps1`
- `tools/validate-contracts.py`
- Branch pushed to `origin/codex/e2e-developed`

## Governance Blocker

Attempted to create GitHub issue `GAP-ARCH-001` in `agarha/mercato-suite`; GitHub connector returned `403 Resource not accessible by integration`. Until issue-write access is granted, open gaps are tracked locally in `GITHUB_ISSUE_BACKLOG.md` and must be synced to GitHub Issues.
