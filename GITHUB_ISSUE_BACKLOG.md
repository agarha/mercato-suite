# GitHub Issue Backlog To Sync

GitHub issue creation is currently blocked for this session by `403 Resource not accessible by integration`. These issue specs must be created in `agarha/mercato-suite` after issue-write permissions are granted.

## Required Labels

- `gap`
- `P1`
- `P2`
- `architecture`
- `business`
- `database`
- `api`
- `ux`
- `security`
- `qa`
- `devops`
- `ai`
- `blocked-external`

## Open P1 Gaps

| Gap | Title | Owner placeholder | Acceptance criteria |
|---|---|---|---|
| GAP-ARCH-001 | Publish Plugin SDK contract for third-party authors | Platform Eng | SDK docs/package published; manifest schema and CI lint documented; README links evidence. |
| GAP-ARCH-002 | Confirm Kafka/MSK ADR | Architecture | ADR confirms Kafka/MSK; runtime config and relay docs match decision. |
| GAP-ARCH-003 | Confirm OpenSearch vs Algolia ADR | Architecture | ADR confirms MVP search path; implementation/defer evidence documented. |
| GAP-PRD-001 | Finalize commission tier numbers | CPO + Finance | Finance-approved commission tier table added; tests updated if rules change. |
| GAP-BRD-001 | Finalize US Marketplace Facilitator state map | Compliance + Legal | Legal-approved state map documented; tax handling scope updated. |
| GAP-BRD-002 | Add per-jurisdiction legal templates | Legal | US/EU/UK/CA templates completed or MVP deferral signed off. |
| GAP-FSD-004 | Resolve snapshot retention vs PII erasure | DPO + Eng | Retention/anonymization policy documented and reflected in DSAR workflow. |
| GAP-SRS-001 | Measure sustained order ingestion | SRE | k6 scenario exists; staging run report attached; thresholds evaluated. |
| GAP-SRS-003 | Validate Tier-0 RPO by restore drill | SRE | Backup/restore drill report with checksum and RPO measurement. |
| GAP-DB-001 | Add monthly partition maintenance job | DB Architect | Script/CronJob exists; test validates next partition creation. |
| GAP-SEC-002 | Finalize DSAR identity verification | DPO | Identity verification process documented and implemented or externally blocked. |
| GAP-QA-001 | Author top-30 Playwright scenarios | QA | 30 specs added; top MVP workflows pass locally/CI. |
| GAP-QA-002 | Add k6 NFR scripts | SRE + QA | k6 scripts for checkout/orders/outbox exist; runner documented. |
| GAP-DEV-001 | Schedule quarterly DR drill cadence | SRE | DR calendar/runbook/owner documented; first drill issue scheduled. |
| GAP-DEV-002 | Validate active/passive failover | SRE | Staging/prod failover dry-run report attached or cloud blocker recorded. |
| GAP-AI-002 | Author AI golden evaluation sets | AI + PM | Phase 3 golden set plan/issues created; MVP deferral recorded. |
| GAP-AI-004 | Procure zero-retention provider contracts | Procurement | Vendor contract status recorded; blocker owner assigned. |
| GAP-X-003 | Add tenant-aware Grafana dashboards | SRE | Dashboard JSON/ConfigMap includes tenant variable and core panels. |

## Open P2 Gaps

| Gap | Title | Owner placeholder | Acceptance criteria |
|---|---|---|---|
| GAP-ARCH-004 | Finalize Qdrant vs pgvector | AI Architecture | ADR/post-MVP issue confirms vector store decision. |
| GAP-ARCH-006 | Design tenant migration pooled-to-silo runbook | SRE | Runbook includes data copy, DNS, downtime, rollback, validation. |
| GAP-ARCH-007 | Close outbox relay language gap | Platform Eng | ADR-006 and implementation evidence confirm Go relay. |
| GAP-PRD-002 | Load-test AI completion limits | PM + Finance | Cost projection and load-test plan attached. |
| GAP-PRD-003 | Choose Phase-1 launch locales | CPO | Locale list approved; i18n backlog updated. |
| GAP-PRD-004 | Instrument time-to-first-sale metric | Data | Event chain and dashboard metric implemented. |
| GAP-BRD-003 | Draft service-credit policy wording | Legal + CSM | Approved wording in legal docs. |
| GAP-BRD-004 | Review regional category list | Compliance | Regulator-reviewed category list published. |
| GAP-FSD-001 | Define dispute terminal states | PM + Eng | DDL/spec updated; workflow tests added. |
| GAP-FSD-002 | Implement KYC webhook retry policy | Eng | Inbox/retry/backoff tests pass. |
| GAP-DB-002 | Benchmark pooled-mode trigger overhead | DB Architect | Benchmark report attached. |
| GAP-DB-003 | Implement DB role grant audit | Security + DB | Grant audit script and CI check added. |
| GAP-API-002 | Add per-endpoint rate-limit table | API | OpenAPI extensions and runtime limiter implemented. |
| GAP-UX-001 | Optimize SPA for mobile web | UX + Eng | Mobile breakpoint tests pass. |
| GAP-UX-002 | Publish tenant token JSON schema | UX + Eng | Schema validates branding/settings saves. |
| GAP-UX-003 | Design Enterprise SSO config flow | UX | Flow/wireframes and backlog issue added. |
| GAP-UX-004 | Build microcopy library in launch locales | UX Writer | String catalog and locale files added. |
| GAP-SEC-003 | Roll out vendor WebAuthn | Security | Phase 2 implementation plan and tests. |
| GAP-SEC-004 | Finalize SIEM choice | Security + SRE | SIEM selected; log forwarding plan updated. |
| GAP-SEC-005 | Implement CSP nonce strategy | Web Eng | Headers/nonces tested; inline scripts removed or nonce-bound. |
| GAP-QA-005 | Choose Litmus or Chaos Mesh | SRE | Chaos tool ADR and first experiment plan. |
| GAP-DEV-003 | Add per-tenant cost attribution | FinOps | Terraform tags and CUR ETL plan. |
| GAP-DEV-004 | Decide Linkerd vs Istio vs Cilium | SRE | Service mesh ADR updated. |
| GAP-DEV-005 | Validate canary thresholds | SRE | Canary rollout analysis and alert thresholds. |
| GAP-AI-001 | Define Qdrant production deployment | AI Eng | EKS/Qdrant deployment pattern and snapshot plan. |
| GAP-AI-005 | Tune Presidio false positive rate | AI Eng | Test corpus and custom recognizers plan. |
| GAP-X-002 | Review per-locale legal text | Legal | Legal-reviewed locale text recorded. |
| GAP-X-004 | Select status page tooling | SRE | StatusPage/default alternative selected and runbook written. |
| GAP-X-005 | Start SOC-2 Type II audit | Compliance | Auditor selected; evidence repository plan started. |
