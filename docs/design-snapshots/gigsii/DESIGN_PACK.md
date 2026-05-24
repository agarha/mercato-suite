# Gigsii — Design Review Pack

One-stop URL pack for handing to any design tool.
Generated 2026-05-23 from branch `claude/gigsii-design-snapshots`, updated
2026-05-23 on `claude/gigsii-task-first-design` to ship the Task-First
direction as a **Gigsii-only theme override** (Mercato defaults unchanged).

> **What changed in the Task-First pass:** the home page now renders the
> Gigsii-specific *Task-First* direction from the design canvas. Polaroid
> hero photo card, big conversational input, three-step "how it goes",
> provider cards, dark CTA. Cream + sage/peach/butter palette with
> Instrument Serif italic accents. The PHP renderer dispatches to
> `page-taskfirst.php` only when the tenant config sets
> `storefront.theme = "taskfirst"`. Mercato default tenants still render
> the original `page.php` layout.

---

## 1. Codebase

Repo:
```
https://github.com/agarha/mercato-suite
```

Branch with everything stacked (storefront templates + Gigsii brand + snapshots + tenancy fixes + adapter modules):
```
https://github.com/agarha/mercato-suite/tree/claude/gigsii-design-snapshots
```

Design-relevant source files inside the repo:

| What | Path |
|---|---|
| Storefront design system — Mercato default (CSS tokens, type scale, components, motion, breakpoints) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/assets/css/storefront.css` |
| Storefront design — Task-First overlay (Gigsii-only) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/assets/css/storefront-taskfirst.css` |
| Storefront page templates (default `page.php` + Gigsii-only `page-taskfirst.php`) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/` |
| Section partials (header, hero, metrics, categories, services, vendors, buyer, requests, features, operations, seller, workflow, footer) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/partials/` |
| Gigsii brand config (hero copy, positioning cards, seller steps, workflow steps, metric labels) | `tools/seed-gigsii-tenant.ps1` lines 38–101 |
| Default tenant brand defaults (every tenant inherits these unless overridden) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Storefront/Config.php` `defaults()` method |
| Code review (Phase-1 assessment of the whole codebase) | `docs/reviews/CODE_REVIEW_POST_CODEX.md` |
| Multi-tenancy audit | `docs/reviews/MULTI_TENANCY_AUDIT.md` |
| Stripe payment-intents route audit | `docs/reviews/STRIPE_PAYMENT_INTENTS_AUDIT.md` |

## 2. Rendered Gigsii storefront pages

These render fully via jsDelivr (Google Fonts + CSS load correctly). Use these URLs for any tool that needs to see the actual visual.

| Page | URL |
|---|---|
| Index of all snapshots | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/index.html |
| Home | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/home.html |
| Services catalog | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/services.html |
| Provider directory | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/providers.html |
| Provider profile — MapleFix | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/provider-detail-maplefix.html |
| Provider profile — BrightNest | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/provider-detail-brightnest.html |
| Provider profile — UrbanSpark | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/provider-detail-urbanspark.html |
| Provider dashboard | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/provider-dashboard.html |
| Buyer account | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/account.html |
| Post a request | https://cdn.jsdelivr.net/gh/agarha/mercato-suite@claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/request-new.html |

## 3. Raw source URLs

If the design tool just wants the HTML or CSS as plain text (no rendering):

| What | URL |
|---|---|
| Storefront CSS | https://raw.githubusercontent.com/agarha/mercato-suite/claude/gigsii-design-snapshots/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/assets/css/storefront.css |
| Home page rendered HTML | https://raw.githubusercontent.com/agarha/mercato-suite/claude/gigsii-design-snapshots/docs/design-snapshots/gigsii/home.html |
| (same pattern for any other page) | replace `home.html` with the page name |

## 4. Context the design tool should know

- **Tenant:** Gigsii (services marketplace for home services). With the Task-First theme: cream `#fff8ee` base, sage `#b8d4c0`, peach `#ffd5b8`, butter `#ffe79b`, deep navy `#1f2a52` text, peach-deep `#ff8a5b` CTA.
- **Design system — default Mercato:** Inter type, 1.25× type scale, deep evergreen brand. Unchanged by this pass.
- **Design system — Task-First overlay (Gigsii-only):** Geist sans + Instrument Serif italic acce