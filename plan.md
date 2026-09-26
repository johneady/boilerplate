# Application Review — Outstanding Items

**Branch:** `misc-updates-and-fixes` (vs `main`) · **Date:** 2026-09-26 (after implementation)
**Status:** Priority 1 and Priority 2 are complete — commit `67b562f` (schema indexes, avatar eager-load) and commit `869d040` (widget cache, DRY sweep, immutable casts, `MoneyInput::positive()`, `User::payments()`), both reviewed, Pint/Larastan clean and the full suite green (1646 tests). What remains is below.

---

## Follow-ups discovered during implementation

| Finding | Where | Note |
| --- | --- | --- |
| The users table's cross-page "select all" count materializes every record (Filament's `checkIfRecordIsSelectableUsing` path), and the new avatar eager load rides that query. Bounded — one extra IN query, ≤1 avatar row per user — but the materialization itself is a pre-existing per-render cost | `UserResource::table()`, pinned by `UserResourceTest` ("fixed number of media queries") | Revisit if the users table grows large: a lightweight selectable check or page-scoped selection |
| Finding 12's second cited caller (`ManagesStripeSubscriptions`) turned out to be a `BillingCustomer` lookup, not payments; only the payments caller was routed through the new `User::payments()` | `ManagesStripeSubscriptions::billingCustomer()` | A `User::billingCustomers()` relation is the equivalent DRY step if those two lookups bother anyone |
| The widget metrics cache (old finding 5) was implemented per the plan's constraints without a post-index measurement | `app/Filament/Widgets/CachesWidgetMetrics.php` | Measure real dashboards under load; relax or remove the 60s TTL if the indexes alone are fast enough |

---

## Priority 3 — accepted trade-offs, revisit at scale

| Finding | Current mitigation | Escape hatch |
| --- | --- | --- |
| MRR hydrates active subscriptions + prices to sum in PHP (`BusinessMetrics.php:154-169`) | 2 queries, row count = paying subscribers | SQL join/sum over `plan_prices` once subscriber counts reach the thousands |
| `dailyNetRevenue()`/`monthlyNetRevenue()` hydrate period rows with a `CarbonImmutable::parse` each (`BusinessMetrics.php:71-121`) | Deliberate, documented (timezone-portable bucketing); served by the ledger index | The widget cache absorbs repeat reads before any SQL rewrite |
| `User::subscribed()` loads subscription + plan models to answer a boolean (`User.php`) | One query + eager plan over the user's 0–2 rows; access rules (`grantsAccess()`) need the models | Push `$planKey` into SQL only if profiling shows it. The `user_id` index is in place |
| Navigation badges (disputes, webhooks, contact) COUNT per full page load | Filament's `Sidebar` is an isolated Livewire component that re-renders only on `refresh-sidebar`, **not** on every Livewire update; all three counts hit existing indexes (`disputes(status,mode)`, `webhook_events(status,created_at)`, `contact_submissions.handled_at`) | 60s cache only if these tables get large |
| Logo row read once per pageview (`Settings.php:199-227`) | Memoised per request; the query is served by `media_owner_collection_index` | TTL cache invalidated by `forgetLogo()`, mostly worthwhile for the disk-exists check on remote disks |
| `newCustomers()` counts users by `role` + `created_at` range, twice per dashboard | `role` is indexed; now behind the widget cache | `['role','created_at']` composite when the customer base grows |
| `users.name` sortable/searchable + default sort, unindexed | Small table | Index `name` for the default sort when the customer base grows |
| Receipt PDFs render inline in the request cycle (`ReceiptPdf.php:48`, `DownloadReceiptController.php:19`) | Throttled 10/min/receipt + 30/min/IP; logo cached 24h; font subsetting; email attachments already queue-rendered | Cache rendered bytes keyed on `payment.id` + `updated_at` |
| Contact email sends SMTP synchronously (`Livewire/Contact.php:200`) | Persist-first + try/catch, documented | Queue `ContactSubmissionReceived`, since a worker is already standard in this stack |
| Unauthenticated `/health` runs DB + cache probes unthrottled (`HealthController.php:40`) | Cheap probes | Generous `throttle:` limiter (60/min per IP), without breaking the container healthcheck |
| Honeypot/timing rejections return before `RateLimiter::increment()` (`Contact.php:99-109`) | Each request still costs a Livewire round trip | Increment the limiter (or a separate key) on every validated submit |
| Sitemap loads all published pages per request (`SitemapController.php:99`) | Documented threshold ("past a few hundred URLs") | Short-TTL cache once sites grow |
| Gateway-id `varchar(255)` columns inside composite unique indexes (`payments`, `subscriptions`, `webhook_events`) | MySQL 8 handles it; real ids ≤ ~64 chars | Size to 64–100 **in the create migrations** |
| `ProcessUploadedImage` decodes the source once per conversion plus once for orientation (`:87-114`) | Queued; documented Intervention v4 mutation rationale | One `clone`d decode if v4 immutability is verified. Measure first |
| `PlanPrice::findByGatewayRef()` scans gateway-ref rows in PHP (`:208`) | Comment justifies it (handful of rows, DB-portable) | Lookup table if the catalogue grows |
| `SubscriptionFactory` re-fetches the price row per creation (`:42`) | Test-only cost | Resolve from the created price in `afterMaking` |
