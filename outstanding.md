# Outstanding Work — Pre-Merge Remediation of `payment-gateway-preparation`

Handoff for completing the remaining phase of the review-driven remediation
started on 2026-09-25. Read this top to bottom before touching anything.

## Mission & ground rules

A multi-angle review of the branch found findings; the owner approved a
3-phase fix plan with this **cadence per phase: implement → independent
code-review of the diff → fix findings → Pint/PHPStan/full Pest green →
`git add` (stage only, NEVER commit) for the owner's approval.**

Owner decisions already made:

- **`RUN_SEEDERS` stays `true`** in `docker-compose.dokploy.yml` — this is a
  demo-first boilerplate; do not "fix" it.
- **No new migrations.** Edit the branch's existing `2026_09_23_*`
  create-migrations in place — the boilerplate is never deployed with data
  worth converting. (The local dev DB was already `migrate:fresh`ed with the
  folded edits.)

## Current git state

Phases 1 and 2 were committed by the owner in `ae4a1b7`. Phase 3 is closed
and **staged** (uncommitted) for the owner's approval.

The owner works in the repo between messages (they commit and stage on their
own; e.g. `.claude/skills/demo/SKILL.md` is their staged change). Do not
sweep up or revert their changes; stage only files this work touched.

## DONE — Phase 1 (committed in `ae4a1b7`)

1. **`GatewayUnavailable` unknown-outcome semantics** — `StartCheckout`,
   `CancelSubscription`, `StartSubscription` now catch `GatewayUnavailable`
   before `GatewayException`, and leave the payment pending / the cancel
   schedule recorded / the subscription incomplete. No `report()` in the
   actions — the Livewire callers (`PayPaymentLink`, `Pricing`, `Billing`)
   already report. Tests: `CheckoutFlowTest` (`checkoutsThrough` helper + 2
   new tests), `SubscriptionLifecycleTest` (+2 new tests),
   `PayPaymentLinkTest` assertion `[Failed, Pending]` → `[Pending, Pending]`.
2. **Mail password encryption** — `SettingKey::isEncrypted()` includes
   `MailPassword`; the field routes through `credentialField()`
   (`autocomplete('new-password')`, masked "Saved: …" hint); the mailer modal
   is write-only (blank keeps stored) with a `remove_mail_password` toggle
   (a typed password wins); i18n keys in `lang/en/settings.php`
   (`remove_password`, `remove_password_help`); 4 tests in
   `ManageSettingsTest`. The data migration was deleted per the owner's
   no-new-migrations rule; a pre-existing plaintext row degrades to `''`
   plus one reported `DecryptException`, and is fixed by re-saving.
3. **`tests/Fixtures/Payments/HeldBooking::createTable()`** —
   `Schema::dropIfExists` before create (fixes the MySQL/MariaDB CI matrix
   DDL-implicit-commit fatal).
4. **`app/Payments/Actions/ReconcilePayment.php` (~:194)** —
   `refunds.failure_reason` truncated to 255 like its sibling paths.

## DONE — Phase 2 (reviewed, verified green; committed in `ae4a1b7`)

Closed in the second session: independent review run on the full diff, its
findings fixed, Pint/PHPStan/prettier clean, full Pest green (1480; the only
failure was the known dev-stack browser flake — AccessibilityTest passes
31/31 standalone).

- `ReconcileStalePayments`: new batch reconciles live subscriptions holding
  no slot, re-claiming `active_user_id` (+test in `SubscriptionCommandsTest`).
- Plans N+1: `isSynced` in the Stripe/PayPal/simulated subscription drivers
  uses the `$plan->prices` relation property (PayPal also looks the combined
  tax up once per call); `PaymentDiagnostics` eager-loads prices;
  `PaymentManager::switchedOn()` extracted and diagnostics derive from it.
- Stripe: `refundsFor` and `paidInvoices` auto-page, drained inside `call()`
  so a failure on a later page is still `GatewayUnavailable`/`GatewayException`
  (+page-2 outage test in `StripeDriverTest`); `checkoutExpiresAt()` clamp
  extracted once into `StripeDriver`.
- PayPal: `transactions()` asks in ≤31-day windows, deduped by transaction
  id, starting from the last paid invoice minus 31 days (`created_at` only
  before anything is recorded) so cost doesn't grow with subscription age
  (+multi-window test in `PayPalSubscriptionTest`). Swap idempotency key
  lives on `Subscription::swapIdempotencyKey(PlanPrice $to)`.
- `Gateway::supportsManualCapture()` removed (dead). `ReconcilePayment::applyRefund`
  sets `last_reconciled_at`.
- **Migrations folded into the existing creates**: `233839 webhook_events`
  gained `(status, created_at)`; `233836`/`233838`/`233842` explicit
  `payment_id` indexes; `233834 payment_links` a `settled_payment_id` index;
  `billing_customers` split out of `233841` into
  `2026_09_23_233837_create_billing_customers_table.php`.
- `ManageSettings` uses `SettingKey::MailMailer->value` instead of the
  `'mail_mailer'` string.
- Lang: dead keys removed; relation-manager titles use `payments.ledger` /
  `payments.refund.plural_label`; `fields.recorded_by` restored for the new
  "Recorded by" infolist entry on manual payments (`PaymentResource`).
- **Badge i18n**: enum labels wrapped in `__()` at every Filament display
  site (10 payment enums + BillingInterval options + settings webhook-URL
  lines, the latter via a `payments.settings.webhook_url` placeholder); ~34
  English-as-key entries in `lang/en.json`; `TranslationsTest` dataset fails
  when a listed enum case has no catalogue entry. `.ai/rules/i18n.md` updated
  to document enum labels as English-as-key.
- `MoneyInput` and `PayPaymentLink::pay()` build the amount regex from
  `$currency->decimals()`.
- Payment-link `DeleteAction` shown disabled with a tooltip when the link has
  payments; `PlanResource` name/key searchable.
- `AccessibilityTest`: pricing, pay, signed receipt, subscriber billing and
  ten admin payment screens (Closure-typed bound datasets).
- `WebhookTest`: Stripe replay beyond tolerance → 400; PayPal with no webhook
  ID → 404. Redundant `Http::preventStrayRequests()` removed from seven test
  files (`TestCase::setUp()` applies it).
- CI `format` job runs `npm run format:check` (prettier over JS/CSS/
  `vite.config.js`; `.prettierrc` matches house style: single quotes, width
  150, Tailwind plugin). Build de-dup skipped (build is ~1.5s).
- `.ai/rules/payments.md` + index entry: only `App\Payments\Actions` write
  payment state; drivers write only gateway references.

Reversed after review:

- **Security headers middleware removed** (owner's decision): nginx's
  `docker/nginx/security-headers.conf` already sets them on every response,
  and HSTS is documented as Traefik's. The Phase 2 rewrite had deleted six
  nginx guard tests in `SecurityHeadersTest`; they are restored.
- **Prune commands reverted to HEAD**: `->limit()->delete()` IS batched
  (MySQL appends LIMIT; SQLite/Postgres rewrite to a rowid/ctid subquery).

## DONE — Phase 3 (reviewed, verified green; staged)

1. **Notes files.** `plan.md` and `todo.txt` deleted outright (`git rm`;
   content in history, `git show ae4a1b7:plan.md`). `UPWORK.txt` stays
   **tracked**: each demo/* branch commits its own proposal there (the
   /demo skill). The first attempt (`git rm --cached` + `.gitignore`) was
   reversed after review, because every branch still tracks these files
   and git silently overwrites/deletes ignored files on checkout. Nothing
   is gitignored; `.dockerignore` gained `UPWORK.txt` and `plan.md` next to
   `todo.txt`, so none ship in images built from the working tree.
2. `docker/new-demo.sh` per-slot MariaDB user — **dropped by the owner.**
3. `laravel/chisel` (unused starter-kit leftover in prod `require`) and
   `laravel/sail` (dev) removed; the lock lost only those two packages.
4. Global `~/.kilocode/rules/CLAUDE.md` (outside the repo) trimmed from 581
   to 144 lines of version-agnostic rules: no version list, no
   package/version sections, Boost section rewritten to Boost 2's tools,
   project guidelines take precedence. Original backed up at
   `~/.kilocode/CLAUDE.md.pre-trim.bak`. `boost:update` changed one line of
   the project `CLAUDE.md` (upstream enum-naming wording).
5. **`PlanSeeder`**: Starter / Pro (14-day trial) / Business, monthly and
   yearly in CAD and USD. Called from `DatabaseSeeder` behind the dev-login
   gate (never production). Seeds only an **empty** catalogue (plans can't
   be deleted, so samples beside someone's own plans couldn't be cleared;
   also makes redeploys a no-op), in one transaction, without factories.
   Seeded plans aren't synced or audited (DatabaseSeeder mutes model
   events); `StartSubscription` syncs on demand, and the Demo gateway needs
   none. `PlanSeederTest` (10 tests). Factory states: only
   `PlanPriceFactory::inactive()` was added (one caller); Payment
   per-status and Refund `succeeded()` states had no callers, and the
   PaymentFactory docblock steers tests to the real actions.
   The five comments citing `plan.md` were reworded to stand alone.

Verification: Pint, PHPStan (0), Prettier clean; Unit+Feature 1448 passed,
1 skip; Browser 38/39 with the known home-page dev-stack flake,
AccessibilityTest 31/31 standalone.

Still open for the owner: Blade formatting (Pint `--blade` / the unused
`prettier-plugin-blade` devDependency).

## Accepted-by-design (do NOT change; already documented in-repo)

- `TRUSTED_PROXIES=*` (config/trustedproxy.php docblock + env examples
  explain the Dokploy unpublished-ports shape).
- PayPal webhook tolerance ±600s vs Stripe 300s (documented clock-drift
  rationale; replays are dedupe no-ops anyway).
- Webhook payload PII kept for the 90-day retention (prune is the control).
- Non-expiring signed receipt URLs (receipts are emailed; APP_KEY hygiene).
- Gateway-id unique keys omitting `mode`; `plan_prices` without a composite
  unique (would break the replaced-inactive-price flow — sync signature is
  the dedup); `payments.tax_lines` NOT NULL without default (consistent
  contract); navigation-badge COUNT queries; `Http::assertSentCount`
  precision; rate-limit test pre-fill; root container (documented
  supervisor layout); `tests/Pest.php` non-Linux lock fallback.

## Gotchas

- **Browser tests flake while the owner's dev stack runs** (Vite on
  127.0.0.1:5174 with `public/hot` present, live since Sep 23):
  `AccessibilityTest` fails with "Execution context was destroyed" during
  the axe scan. It passes standalone — environmental, not a code issue. Do
  not kill the owner's dev stack.
- The local dev DB was already `migrate:fresh`ed against the folded
  migrations; the test suite uses sqlite `:memory:` and is unaffected.
- Baseline at Phase 1 close: Pint clean, PHPStan 0 errors, Pest
  1446-1448 tests with 1 intentional skip. The one `risky`/browser flake is
  the dev-stack issue above.
- Never commit — stage for approval only.
- The original six review reports (payments-core, security, database,
  Filament/frontend, tests/CI, base-app/ops) have file:line detail if any
  finding needs re-verification.
