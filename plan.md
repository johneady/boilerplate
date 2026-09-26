# Application Review — Outstanding Items

**Branch:** `misc-updates-and-fixes` (vs `main`) · **Date:** 2026-09-26 (after implementation)
**Status:** Priority 1 and 2 are complete (`67b562f`, `869d040`). A reviewed follow-up pass over the remaining items landed the decisions below; what is left on this page is deliberately deferred, each with the reason.

**Implemented in the follow-up pass:**
- `User::billingCustomers()` relation; the Stripe driver's two ad-hoc lookups route through it.
- `users.name` index in the create migration (serves the users table's default sort; search stays unindexed on purpose — leading-wildcard LIKE).
- `ContactSubmissionReceived` is queued (`ShouldQueue`, `deleteWhenMissingModels`); the visitor no longer waits on SMTP and a failed send is covered by the failed-job alert. `.ai/rules/models-notifications.md` updated to record the new decision.
- `/health` throttled at 60/min per IP (`throttle:health`); the container HEALTHCHECK polls `/up`, so nothing that restarts a container is affected. `.ai/rules/controllers-listeners.md` updated.
- Honeypot/timing rejections increment their own per-address budget (`contact-form-rejections:`, 30/hour) and the form refuses the address once it is exhausted — humans' limiter untouched.
- Gateway-id columns sized to 100 in the create migrations for `payments`, `subscriptions`, `webhook_events` and `billing_customers`.
- `ProcessUploadedImage` decodes the source once and deep-clones per conversion (verified: both drivers' `Image::__clone` deep-copy; modifiers still mutate in place). `.ai/rules/concerns.md` updated.
- `SubscriptionFactory` resolves `plan_id` through a per-instance memo, so runs that reuse one price read it once.

---

## Remaining — accepted trade-offs, revisit at scale

| Finding | Current mitigation | Escape hatch |
| --- | --- | --- |
| The users table's cross-page "select all" count materializes every record (Filament's `checkIfRecordIsSelectableUsing` path), and the avatar eager load rides that query. Bounded — one extra IN query, ≤1 avatar row per user | Pinned by the fixed-query-count test in `UserResourceTest` | Revisit if the users table grows large: a lightweight selectable check or page-scoped selection |
| The 60s widget metrics cache is unmeasured (indexes landed alongside it) | `app/Filament/Widgets/CachesWidgetMetrics.php` | Measure real dashboards under load; relax or remove the TTL if the indexes alone are fast enough |
| MRR hydrates active subscriptions + prices to sum in PHP (`BusinessMetrics.php`) | 2 queries, row count = paying subscribers | SQL join/sum over `plan_prices` once subscriber counts reach the thousands |
| `dailyNetRevenue()`/`monthlyNetRevenue()` hydrate period rows with a `CarbonImmutable::parse` each (`BusinessMetrics.php`) | Deliberate, documented (timezone-portable bucketing); served by the ledger index and the widget cache | SQL rewrite only if profiling ever demands it; not portable across SQLite/MySQL/MariaDB without care |
| `User::subscribed()` loads subscription + plan models to answer a boolean | One query + eager plan over the user's 0–2 rows; access rules (`grantsAccess()`) need the models | Push `$planKey` into SQL only if profiling shows it |
| Navigation badges (disputes, webhooks, contact) COUNT per sidebar refresh | Filament's `Sidebar` is an isolated Livewire component that re-renders only on `refresh-sidebar`; all three counts hit existing indexes | 60s cache only if these tables get large |
| Logo row read once per pageview (`Settings.php`) | Memoised per request; served by `media_owner_collection_index` | TTL cache invalidated by `forgetLogo()`, mostly worthwhile for the disk-exists check on remote disks |
| `newCustomers()` counts users by `role` + `created_at` range, twice per dashboard | `role` is indexed; behind the widget cache | `['role','created_at']` composite when the customer base grows |
| Receipt PDFs render inline in the request cycle (`ReceiptPdf.php`, `DownloadReceiptController.php`) | Throttled 10/min/receipt + 30/min/IP; logo cached 24h; font subsetting; email attachments already queue-rendered | Cache rendered bytes keyed on `payment.id` + `updated_at` |
| Sitemap loads all published pages per request (`SitemapController.php`) | Documented threshold ("past a few hundred URLs") | Short-TTL cache once sites grow |
| `PlanPrice::findByGatewayRef()` scans gateway-ref rows in PHP | Comment justifies it (handful of rows, DB-portable) | Lookup table if the catalogue grows |
