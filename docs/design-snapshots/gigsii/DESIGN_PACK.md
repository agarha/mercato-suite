# Gigsii — Design Review Pack

One-stop URL pack for handing to any design tool.
Generated 2026-05-23 from branch `claude/gigsii-design-snapshots`.

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
| Storefront design system (CSS tokens, type scale, components, motion, breakpoints) | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/assets/css/storefront.css` |
| Storefront page templates | `apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/` |
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

- **Tenant:** Gigsii (services marketplace for home services). Brand is deep evergreen + warm amber accent.
- **Design system:** Inter type, 1.25× type scale, 4 elevation tiers, 8/12/16/24/32/48/64 spacing, prefers-reduced-motion respected.
- **A11y baseline:** WCAG 2.1 AA contrast on body text, skip-link, semantic landmarks (`<header role="banner">`, `<main id="main">`, `<footer role="contentinfo">`), ARIA-labeled regions, visible focus rings using `--accent`.
- **Three seeded providers** with realistic copy: MapleFix (plumbing), BrightNest (cleaning), UrbanSpark (electrical).
- **Ten sample reviews** spread across them (mix of 4-star and 5-star, plausible service-marketplace text).
- **Server-rendered:** PHP templates inside WordPress, not a React SPA. Tenant-agnostic — same template stack drives any future Xusmo tenant.

## 5. What I'd ask a design reviewer to look for

- Visual rhythm across the hero → metrics → sections cascade.
- Card density on the services index and provider directory.
- Star-rating treatment (Unicode glyphs `★ ⯨ ☆` — could be SVG later).
- Empty states (visible on `account.html` when not logged in; intentionally not shown in this snapshot which is the logged-in variant).
- Type hierarchy on `provider-dashboard.html` — three sections stacked.
- Form aesthetic on `request-new.html`.

