# Application Review — Best Practices & Performance

**Branch:** `misc-updates-and-fixes` (vs `main`) · **Date:** 2026-09-25 (revised after verification pass)
**Scope:** entire application plus all branch changes (8 commits, `a25dd6b..fddd491`)
**Method:** full sweep of models/migrations/factories/seeders, Filament layer, Livewire/controllers/jobs/notifications/payments domain, routes and config; Larastan and Pint both pass clean. Every finding below was re-checked against the code; see *Revision notes* at the end for what changed and why.

**Project constraint that shapes every schema fix:** this is a boilerplate, so schema changes are made **in the migration that creates the table**, not in new follow-up migrations. Verify with `php artisan migrate:fresh --seed`, and check the seeders for the old shape.

---

## Verdict

Exceptionally well-engineered. Authorization is airtight, queue/webhook/scheduling discipline is textbook, and the branch's new domain code (receipt numbering, business metrics, exports, summary email) keeps to the codebase's standards. The material findings are almost all **missing indexes on growth paths**. Nothing is structurally wrong.

The one change that matters is the `payment_transactions` ledger index. Everything else is small.

---

## Priority 1 — before or shortly after merge

### 1. Index the ledger: `payment_transactions (currency, occurred_at)` — PERFORMANCE, HIGH

`BusinessMetrics::ledger()` (`app/Payments/BusinessMetrics.php:272`) filters `currency = ?` plus an `occurred_at` range, joined to `payments.mode`. The table only indexes `payment_id` and `unique(gateway, gateway_transaction_id)` (`database/migrations/2026_09_23_233836_create_payment_transactions_table.php:33-37`). Every metric (~6 on a dashboard load, plus the summary email) is a full scan of an append-only table that never shrinks.

**Recommendation:** add `$table->index(['currency', 'occurred_at']);` **in the create migration itself**, with a comment in the same style as the neighbouring `payment_id` index. The equality column goes first so the range can use the index.

### 2. Explicit FK indexes the codebase's own convention calls for — PERFORMANCE, MEDIUM

Three sibling migrations add explicit FK indexes "rather than left to MySQL's implicit foreign-key index: SQLite and PostgreSQL create none". These columns don't have them yet:

| Column | Hot path |
| --- | --- |
| `subscriptions.user_id` | `User::subscriptions()` behind `subscribed:` middleware on every gated request; `isEligibleForTrial()`, `currentFirst` |
| `payments.user_id` | `Livewire/Settings/Billing.php:71` (`where user_id … latest('paid_at')`) |
| `subscriptions.plan_price_id` | counted per row in `PricesRelationManager` |

Lower value, but the same one-line sweep: `payment_links.created_by`, `media.uploaded_by`, `payments.recorded_by`.

**Recommendation:** add the indexes in the create migrations, with the existing portability comment.

### 3. Users table avatar N+1 — PERFORMANCE, MEDIUM

The `UserResource.php:113` avatar column calls `avatarUrl()`, which calls `HasMedia::getMedia()`. That runs one media query per row because `media` is never eager-loaded.

**Recommendation:** eager-load only the avatar collection: `->modifyQueryUsing(fn ($q) => $q->with(['media' => fn ($m) => $m->inCollection(MediaCollection::Avatar)]))`. `getMedia()` (`app/Concerns/HasMedia.php:69-75`) already filters a loaded relation in memory, the same pattern `SubscriptionResource` uses. Don't add an extra exists-check memo: `Media::url()` already memoises its disk check per instance, and each row is a different file.

### 4. Default table sorts that can't use an index — PERFORMANCE, LOW–MEDIUM

| Column(s) | Consumer | Fix |
| --- | --- | --- |
| `payments (mode, created_at)` | `PaymentResource.php:242,251`: default sort `created_at desc` under the default `mode` filter. The existing `['status','created_at']` index doesn't help because `status` isn't filtered by default | composite `['mode', 'created_at']` |
| `subscriptions.created_at` | `SubscriptionResource.php:175` default sort | `created_at` index, or `['mode','created_at']` if a mode filter is added to match payments |

~~Index `payments.customer_email`, `audit_logs.user_email`/`user_name` for search~~ **Dropped.** Filament's `->searchable()` issues `LIKE '%term%'` ORed across several columns, and a B-tree index can't serve a leading wildcard. Only revisit this if search moves to prefix/exact matching or full-text search.

---

## Priority 2 — next sprint

| # | Finding | Location | Recommendation |
| --- | --- | --- | --- |
| 5 | Short-TTL cache for dashboard metrics. A dashboard load runs ~16 aggregates (`BusinessOverview` ~11, `RevenueChart` 1, `NeedsAttention` 4), but the widgets are **lazy** (Filament default), so the load is split across three deferred requests and doesn't block the page. Once finding 1 lands these are cheap | `app/Filament/Widgets/*`, `BusinessMetrics` | Measure after finding 1 first. If still needed, `Cache::remember` with a 60s TTL in the **widgets** (not `BusinessMetrics`: the summary email must read fresh figures). Key on mode + currency + **period start**, never on the `$now` bound (`netRevenue($thisMonth, $now)` changes every second, so an argument-keyed cache would never hit). TTL only, with no event-driven invalidation: a minute of staleness is fine for month-level figures |
| 6 | Active-tax-rate lookup `TaxRate::query()->active()->get()->map->toCalculatorRate()` copy-pasted at 4 sites (plus Stripe driver variant) | `StartCheckout.php:109`, `RecordManualPayment.php:70`, `PayPaymentLink.php:113`, `SimulatesSubscriptions.php:259`, `ManagesStripeSubscriptions.php:349` | **DRY, not performance:** each runs once per request. Extract `TaxRate::activeCalculatorRates()`. Don't cache it, because charging a stale rate after an admin edit is worse than one tiny query |
| 7 | `PaymentLinkPolicy::delete()` queries `payments()->exists()` per row while the table already loads `payments_count` | `PaymentLinkPolicy.php:34` vs `PaymentLinkResource.php:144` | `($model->payments_count ?? null) !== null ? $model->payments_count > 0 : $model->payments()->exists()` |
| 8 | "Started subscription statuses" derived in more than one place | `User::isEligibleForTrial()` (`User.php:298`), `User::subscribed()` (hand-listed, `User.php:327`), `Subscription::currentFirst` (`Subscription.php:291`) | `SubscriptionStatus::started()` used by all. Note that `subscribed()` deliberately lists a *different* set (it excludes `Ended`), so give that one its own named helper rather than forcing it to share |
| 9 | Docblocks promise `CarbonImmutable`, casts produce mutable `Carbon` | `User.php:42,50` (`email_verified_at` cast at `:141`), `ContactSubmission.php:76`, `Setting.php` | Switch the casts to `immutable_datetime` as the other models do. Check the Fortify/`MustVerifyEmail` callers still type-check |
| 10 | Money "amount > 0" validation rule implemented twice | `PaymentLinkResource.php:203` vs `PricesRelationManager.php:44` | `->positive()` helper on `MoneyInput` |
| 11 | Avatar-resolution closures duplicated between form and table, with near-identical comments | `UserResource.php:54,113` | One `static function avatarState(User $record, string $conversion)` helper |
| 12 | No `User::payments()` inverse relation; callers build ad-hoc builders | `Livewire/Settings/Billing.php:71`, `ManagesStripeSubscriptions.php:399` | Add the `hasMany` and route callers through it |

---

## Priority 3 — accepted trade-offs, revisit at scale

| Finding | Current mitigation | Escape hatch |
| --- | --- | --- |
| MRR hydrates active subscriptions + prices to sum in PHP (`BusinessMetrics.php:154-169`) | 2 queries, row count = paying subscribers | SQL join/sum over `plan_prices` once subscriber counts reach the thousands |
| `dailyNetRevenue()`/`monthlyNetRevenue()` hydrate period rows with a `CarbonImmutable::parse` each (`BusinessMetrics.php:71-121`) | Deliberate, documented (timezone-portable bucketing); served by finding 1's index | Cache (finding 5) before any SQL rewrite |
| `User::subscribed()` loads subscription + plan models to answer a boolean (`User.php:324-337`) | One query + eager plan over the user's 0–2 rows; access rules (`grantsAccess()`) need the models | Push `$planKey` into SQL only if profiling shows it. Finding 2's `user_id` index is the real fix |
| Navigation badges (disputes, webhooks, contact) COUNT per full page load | Filament's `Sidebar` is an isolated Livewire component that re-renders only on `refresh-sidebar`, **not** on every Livewire update; all three counts hit existing indexes (`disputes(status,mode)`, `webhook_events(status,created_at)`, `contact_submissions.handled_at`) | 60s cache only if these tables get large |
| Logo row read once per pageview (`Settings.php:199-227`) | Memoised per request; the query is served by `media_owner_collection_index` | TTL cache invalidated by `forgetLogo()`, mostly worthwhile for the disk-exists check on remote disks |
| `newCustomers()` counts users by `role` + `created_at` range, twice per dashboard | `role` is indexed | `['role','created_at']` composite when the customer base grows |
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

---

## Branch-specific notes

- **The export N+1 fix is committed** (`fddd491`). The `currentFirst` scope on `Subscription` plus `modifyQuery()` eager-loading in `UserExporter` removes a 4-queries-per-row N+1, with a query-count regression test in `ExportsTest`.
- **`DemoBusinessSeeder`** replays ~175 sales one at a time through the real actions (`StartCheckout`/`ReconcilePayment`/`RefundPayment`/`StartSubscription`) rather than bulk inserts. This is a deliberate fidelity trade (real ledger, receipt sequence, audit trail), wrapped in a transaction, guarded for idempotence and skipped in tests. No change needed.
- **Correctly reasoned, no findings:** `SendBusinessSummary` (hourly self-scheduling, period claim committed atomically with the queued notifications), `ReceiptNumbers` (gap-free, `lockForUpdate` inside the caller's transaction, per-mode series, deadlock-avoiding seed rows), `PruneExports` (chunked, orphan sweep), the `Role::atLeast()` grants-based rework, and `ResolvesLoginRedirect`.

---

## Particularly strong patterns — preserve as the codebase grows

1. **Authorization:** policies registered explicitly via `Gate::policy()`; deny-by-default `BasePolicy` covering Filament's otherwise-allowed abilities (`deleteAny`, `reorder`, `replicate`, attach/associate); scoped admin-bypass carve-outs (self-delete, immutable financial/audit records).
2. **Webhook pipeline:** signature verification with a mode cross-check, `insertOrIgnore` idempotency on `(gateway, event_id)`, fully queued processing, `WithoutOverlapping` keyed on the subject, `retryUntil()` instead of `$tries`, rate-limited rejection alerting.
3. **`ReconcilePayment`:** gateway read outside the transaction, short `lockForUpdate` transaction, append-only ledger, exactly-once side effects via conditional-update claims with `afterCommit` dispatches.
4. **Scoped service bindings:** `Settings`/`PaymentManager`/`AuditLogger` are scoped, so the settings table loads once per request while queue workers re-read between jobs.
5. **Exports:** Filament's queued/chunked pipeline, eager-loading `modifyQuery()`, CSV formula-injection protection, machine-readable ISO dates/money via a shared `SpreadsheetExporter` base, and a prune schedule for export files.
6. **Scheduling:** `withoutOverlapping()` with explicit expiry + `onOneServer()` on every task, each with written rationale.
7. **Query hygiene:** eager-loading discipline across resources and relation managers, `withCount` over relation `count()`, per-row action cost called out in comments, request-scoped memoisation (`avatarUrl()`, `logoMedia()`), lazy/unpolled dashboard widgets.
8. **Validation strategy:** no Form Requests, but consistently so: shared rule traits reused by Livewire components, Fortify actions, and defensive re-validation in `MediaManager`. Rules are single-sourced, so nothing drifts.

---

## Bottom line

Merge the branch. Then make one pass over the create migrations: the ledger index (1), the FK indexes (2) and the payments/subscriptions sort indexes (4), plus the one-line avatar eager-load (3). Re-run `migrate:fresh --seed` and the payments/dashboard tests. Hold off on caching until the indexes are measured.

---

## Revision notes (verification pass)

What changed from the first draft, and why:

- **All schema fixes now edit the create migrations** instead of adding new ones, following the project's boilerplate convention. The original draft proposed follow-up migrations (findings 1 and the varchar sizing).
- **Ledger index made composite `(currency, occurred_at)`:** `currency` is an equality filter in every ledger query, so it belongs first.
- **Metrics cache demoted from P1 to P2 (#5).** Filament widgets are lazy by default, so the queries don't block the dashboard. The index is the real fix. The draft's plan to cache inside `BusinessMetrics` keyed on arguments would never hit (the `$now` bound changes every second) and would serve cached figures to the summary email. The claim that it would "collapse the duplicate dispute count" was also wrong: the sidebar badge doesn't go through `BusinessMetrics` and runs in a different request.
- **Search-column indexes dropped.** Filament search is `LIKE '%term%'`, which a B-tree index can't use.
- **Nav-badge finding demoted to P3.** Filament 5's sidebar doesn't re-render on every Livewire update, and all three counts are already indexed.
- **FK indexes promoted into their own P1 item (#2)**, since they back the `subscribed:` middleware path.
- **Tax-rate finding recast as DRY only:** each call runs once per request, and caching tax rates risks charging a stale rate.
- **`User::subscribed()` and MRR demoted to P3:** both read a handful of rows. The avatar "memoise exists-check" suggestion was removed because `Media::url()` already does it.
- **"Uncommitted diff" note updated:** it is now commit `fddd491`.
