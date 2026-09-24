# Plan: Online Payments (Stripe + PayPal)

Built-in, dormant-by-default payments so a client project that needs them
switches them on instead of building them: one-time payments, refunds,
authorize/capture, subscriptions, payment links, simple tax, and disputes,
across Stripe (Canada or US), PayPal Business, a credential-free Demo gateway
and manually recorded payments.

One PR on `payment-gateway-preparation`, delivered as **three commits**, one per
phase, each fully tested before it is committed.

---

## Delivery workflow (every phase)

1. **Make the changes** for the phase.
2. **Test**: run the phase's tests (`php artisan test --compact <paths>`).
3. **Fix** until they pass.
4. **Stage**: `git add` the phase's files. Nothing unrelated gets staged.
5. **Code review the staged diff** (`/code-review high` against the staged
   changes), fix what it finds, and re-stage.
6. **Final full test**:
   - `composer test` (config clear, Pint check, PHPStan level 8, full Pest suite in parallel)
   - `vendor/bin/pest --testsuite=Browser`
   - `vendor/bin/pest --testsuite=Sandbox` when sandbox credentials are present (from phase 3 on)
7. **Commit** with a conventional message (`feat(payments): …`).

After phase 3, open one PR to `main` covering all three commits.

Within this branch, a later phase that needs a column on an earlier phase's
table **edits that migration in place**. It does not add an alter migration.

---

## Decisions (agreed)

| Topic | Decision |
| --- | --- |
| What gets paid | A `Payable` contract any model can implement. The boilerplate's own payable is **Payment Links**. |
| Charge types | One-time charges, full and multiple partial refunds, **authorize → capture/void**, **subscriptions** |
| Checkout UX | Hosted redirect (Stripe Checkout; PayPal's approval page via the Orders v2 / Subscriptions API). The driver interface leaves room for embedded checkout later. |
| Gateways | Stripe, PayPal **Business only**, **Demo** (no credentials, non-production only), **Manual** (recorded by an admin) |
| Subscriptions | Our own unified model on top of Stripe Billing and PayPal Subscriptions. No Cashier. |
| Plans | Defined in admin and synced out to the gateways. A price change creates a new price; existing subscribers keep the old one. |
| Subscription features | Trials, cancel at period end + resume, upgrade/downgrade, self-serve `/settings/billing` page |
| Customers | Guests (name + email) can make one-time payments. Subscriptions require a signed-in User. |
| Failed renewals | The gateways retry. The app mirrors `past_due`, emails the customer and ops, and keeps access for a configurable grace period. |
| Credentials | Admin settings, **encrypted**, write-only, separate sandbox and live sets with a mode switch, changes need password confirmation and send an ops alert, webhooks can be registered automatically |
| Currency | One per install (CAD / USD), stored on every row, integer minor units |
| Tax | One configured set of tax lines, a **taxable flag** on each item, **tax-exclusive** pricing |
| Dormancy | Off until an admin enables it. While off, no new payment can start (pay pages 404, admin resources hidden); receipts, returns and webhooks for payments already made keep working. |
| Emails | Customer receipts and lifecycle emails, plus ops alerts. **All queued**, dispatched after commit, sent exactly once. |
| Integrity | Financial records are **immutable** (append-only ledger), every write path is **idempotent**, and state changes run in **short DB transactions** with row locks. See [Integrity](#integrity-transactions-immutability-idempotency). |
| Permissions | Granular Permission cases, no new role |
| Disputes | Tracked and alerted. Evidence is submitted in the gateway dashboard. |
| Payment links | Fixed or customer-entered amount (min/max), single-use or reusable, optional expiry |
| Dependencies | `stripe/stripe-php` only. PayPal goes through Laravel's `Http` client. |
| Testing | Fakes and signed webhook fixtures in CI, plus an opt-in real-sandbox suite |

### Assumptions (confirm or correct before phase 1 starts)

1. **Holds need no built-in UI.** A payable chooses capture or authorize through
   the contract (`captureMethod()`). Payment links always capture. Holds are
   exercised by a test-only payable and managed through the admin Capture/Void
   actions.
2. **One active subscription per user.** Changing plan is a swap, not a second
   subscription.
3. **A single-use link paid twice refunds the duplicate automatically.** This
   covers two checkouts opened concurrently. The duplicate is refunded and ops
   is alerted.
4. **Customer-entered amounts default to not taxable** (donations). The flag can
   still be turned on.
5. **Tax on one-time payments is calculated by us** and sent to the gateway as
   explicit amounts. **Tax on subscriptions is calculated by the gateway**:
   Stripe uses synced TaxRate objects, and PayPal uses one combined plan
   percentage. Renewal payments store the tax the gateway reports.
6. **A partial refund splits its tax back proportionally** across the original
   tax lines (largest remainder). The final refund takes whatever is left so
   the totals reconcile to the cent.
7. **The Demo gateway follows the dev-login gate**: available everywhere except
   `APP_ENV=production`. Normally `.ai/rules/dev-login.md` says to name the safe
   environments rather than exclude production. Here the denylist is justified
   because any instance that is not production already offers passwordless
   admin login, so a fake gateway adds no exposure on top of that.
8. **PayPal "cancel at period end"** suspends the PayPal subscription, and a
   scheduled task cancels it at period end. "Resume" re-activates it. The
   sandbox suite verifies this; if suspend/activate turns out to misbehave
   around the billing date, fall back to cancel-now + re-subscribe with a
   `start_time`.

---

## Architecture

### Layout

A new domain folder `app/Payments/`, following the pattern of `app/Media` and
`app/Audit`. Models stay in `app/Models`, Filament screens in `app/Filament`,
and Livewire components in `app/Livewire`, as today.

```text
app/Payments/
  Money.php                   value object: int minor units + Currency; add/subtract/allocate/format
  Currency.php                enum CAD|USD with decimals() and label()
  Gateway.php                 enum Stripe|PayPal|Demo|Manual with label(), color(), supports(Capability)
  GatewayMode.php             enum Sandbox|Live
  Capability.php              enum OneTime|Refund|Capture|Subscriptions|Swap|Webhooks …
  PaymentStatus.php           enum + allowed transitions (state machine)
  RefundStatus.php, SubscriptionStatus.php, DisputeStatus.php, CaptureMethod.php
  Contracts/
    Payable.php               amount, currency, description, customer, taxable, captureMethod, onPaid()
    PaymentDriver.php         createCheckout, fetchPayment, capture, void, refund
    SubscriptionDriver.php    syncPlan, createSubscriptionCheckout, cancel, resume, swap, fetchSubscription, billingPortalUrl
    WebhookDriver.php         verify(Request): VerifiedWebhook, register(url): WebhookRegistration, fetchSubject()
  Concerns/IsPayable.php      morphMany payments, amountPaid(), balance(), isPaid()
  Drivers/
    StripeDriver.php
    PayPalDriver.php          + PayPalClient.php (OAuth token cached per mode, PayPal-Request-Id idempotency)
    DemoDriver.php
    ManualDriver.php
    Stripe/LaravelHttpClient.php   stripe-php HttpClient adapter over Laravel Http, so Http::fake covers Stripe too
  PaymentManager.php          resolves drivers, lists enabled gateways for the current mode
  PaymentCredentials.php      reads/decrypts the credential settings for a gateway + mode
  Tax/TaxCalculator.php       per-line half-up rounding, returns a TaxBreakdown
  Tax/TaxBreakdown.php
  Actions/                    StartCheckout, ReconcilePayment, RefundPayment, CapturePayment, VoidPayment,
                              RecordManualPayment, StartSubscription, CancelSubscription, ResumeSubscription,
                              SwapSubscriptionPlan, SyncPlan, ReconcileSubscription, RegisterWebhooks
  Webhooks/                   per-gateway event → action mapping
```

### Data model

All money is stored as integer minor units plus a `currency` column. All
gateway-issued IDs are unique per gateway. Every gateway-facing row stores
`mode` (sandbox/live), so sandbox and live data never mix.

| Table | Key columns |
| --- | --- |
| `payments` | `uuid` (public ref), `idempotency_key` (unique), `payable_type/id` (morph; a subscription's payments have the Subscription as payable, so no separate `subscription_id`), `user_id?`, `customer_name`, `customer_email`, `gateway`, `mode`, `status`, `capture_method`, `currency`, `subtotal`, `tax_total`, `amount`, `amount_captured`, `amount_refunded`, `tax_lines` (json snapshot), `description`, `gateway_checkout_id`, `gateway_payment_id`, `authorized_at`, `authorization_expires_at`, `captured_at`, `paid_at`, `receipt_sent_at`, `failed_at`, `failure_reason`, `manual_method?`, `manual_reference?`, `metadata` (json). `amount_captured` / `amount_refunded` are projections recomputed from the ledger, never written directly. |
| `payment_transactions` | **Append-only ledger** of every money movement: `payment_id`, `type` (authorization/capture/refund/void/dispute_debit/dispute_credit/manual), `amount` (signed), `currency`, `gateway`, `gateway_transaction_id` (unique with gateway), `source` (webhook/return/admin/demo/scheduler), `occurred_at`, `created_at` only |
| `refunds` | `uuid`, `idempotency_key` (unique), `payment_id`, `gateway_refund_id` (unique with gateway), `amount`, `tax_amount`, `reason`, `status`, `initiated_by` (user id, null = came from the gateway dashboard), `notified_at` |
| `webhook_events` | `gateway`, `mode`, `event_id` (unique with gateway), `type`, `payload` (json), `status` (received/processed/ignored/failed), `attempts`, `processed_at`, `error` |
| `payment_links` | `token` (unguessable, 32 chars), `title`, `description`, `amount_type` (fixed/customer), `amount`, `min_amount`, `max_amount`, `currency`, `taxable`, `usage` (single/reusable), `expires_at`, `is_active`, `created_by` |
| `tax_rates` | `name` (e.g. "HST"), `rate_milli_percent` (13% = 13000, QST 9.975% = 9975), `is_active`, `sort`, `gateway_refs` (json: Stripe TaxRate IDs per mode; rates are immutable there, so an edit makes a new one) |
| `plans` | `key` (used in `subscribed('pro')`), `name`, `description`, `features` (json list), `trial_days`, `taxable`, `is_active`, `sort`, `gateway_refs` (Stripe Product / PayPal Product per mode) |
| `plan_prices` | `plan_id`, `amount`, `currency`, `interval` (month/year), `interval_count`, `is_active`, `gateway_refs` (Stripe Price / PayPal Plan per mode) |
| `subscriptions` | `user_id`, `plan_id`, `plan_price_id`, `gateway`, `mode`, `gateway_subscription_id`, `status`, `trial_ends_at`, `current_period_start/end`, `cancel_at_period_end`, `canceled_at`, `ends_at`, `past_due_since`, `active_user_id` (unique, nullable: equals `user_id` while the subscription is live and is nulled when it ends. This enforces one live subscription per user on MySQL, MariaDB and SQLite alike, since MySQL has no partial unique index), `idempotency_key` (unique) |
| `billing_customers` | `user_id`, `gateway`, `mode`, `gateway_customer_id` (a table rather than columns on `users`) |
| `disputes` | `payment_id`, `gateway`, `mode`, `gateway_dispute_id`, `currency`, `amount`, `reason`, `status` (won/lost is the outcome), `evidence_due_by`, `closed_at`, `opened_notified_at`. The dashboard link is derived, not stored. A dispute is **not** entered on the ledger: the payment was not refunded, and a won dispute returns the money. |

Payment, Refund, PaymentLink, TaxRate, Plan, PlanPrice, Subscription and
Dispute use `Auditable`. WebhookEvent does not, because its payloads carry
personal data and it has its own retention.

### Integrity: transactions, immutability, idempotency

This applies to every phase. Each rule has a test (see the phase test lists).

**Transactions: short, and never around a gateway call.** A DB transaction
cannot roll back a charge that Stripe has already made, and holding a row lock
across a slow HTTP call stalls every webhook for that payment. So every
state-changing action has three steps:

1. **Record intent**, in `DB::transaction()` with `lockForUpdate()` on the
   payment (or subscription) row:
   - validate against the current locked state (e.g. refundable balance)
   - create the pending Refund / Payment row with its idempotency key
   - commit
2. **Call the gateway** outside any transaction, passing that idempotency key.
3. **Apply the result**, in a second `DB::transaction()` + `lockForUpdate()`:
   - re-read the row
   - append the ledger entry
   - recompute the projections
   - advance the status through the state machine
   - commit

If the process dies between steps 2 and 3, the pending row and its
deterministic key make the operation safe to finish. The same call is retried
and returns the same gateway object, or the webhook or the
`payments:reconcile-stale` task completes it.

Multi-row writes (a payment plus its ledger entry plus a single-use link
closing) always share one transaction. Webhook processing wraps each event's
reconcile in one transaction too. Emails, `Payable::onPaid()` side-effect jobs
and plan-sync jobs are dispatched **after commit** (`afterCommit()` /
`DB::afterCommit()`), so a rolled-back change never sends a receipt.

**Immutability.**

- **Ledger.** `payment_transactions` is insert-only. Its model throws on
  `updating` and `deleting`, and it has no `updated_at` column. Corrections are
  new entries (a refund is a negative entry, never an edit).
- **Financial fields are write-once.** On Payment and Refund these are amount,
  currency, tax lines, customer, payable, gateway, mode, gateway IDs and
  idempotency key. A model `updating` hook throws if any of them changes after
  insert. Only status, timestamps and ledger projections move, and only through
  the actions.
- **Status is forward-only.** It changes through the `PaymentStatus`,
  `RefundStatus`, `SubscriptionStatus` and `DisputeStatus` transition tables.
  An event that would move a status backwards (a late `pending` after
  `succeeded`) is logged and ignored.
- **No deletion.** Payments, refunds, ledger entries and disputes throw on
  `deleting`. In the admin panel they are immutable to everyone, including
  admins, via the same `IMMUTABLE_RECORD_ABILITIES` handling as audit logs.
  Webhook events are also admin-immutable, but they leave through retention
  pruning, which uses a query-builder delete on age alone, the same as the
  audit-log prune.
- **Plan prices and synced tax rates are never edited.** A change creates a new
  row and a new gateway object, and the old one is archived.

**Idempotency.**

| Path | Guard |
| --- | --- |
| Outgoing gateway calls | Deterministic keys (`{payment uuid}:{operation}`, the refund's `idempotency_key`) sent as Stripe `Idempotency-Key` / PayPal `PayPal-Request-Id`. A retried job or request gets the original result instead of a second charge or refund. |
| Gateway catalogue objects (products, prices, tax rates, customers) | Keyed on the record's id **and creation time** (`App\Payments\CatalogueKey`), so two installations sharing a gateway account, or a rebuilt database, never replay each other's objects within the 24-hour idempotency window. |
| Incoming webhooks | `insertOrIgnore` on unique (gateway, event_id); a replayed event is acknowledged and not processed again. |
| Return URL and webhook racing | Both go through `ReconcilePayment` under the row lock. The ledger's unique (gateway, gateway_transaction_id) means the same capture seen twice records once. |
| Pay form double-submit | The rendered form carries an idempotency token that becomes `payments.idempotency_key`. A second submit finds the existing pending payment and redirects to its existing checkout session. |
| Admin refund/capture double-click | The key is generated when the modal mounts, and `refunds.idempotency_key` is unique, so a second submit returns the first refund. |
| Side effects (receipt, `onPaid()`, lifecycle emails) | Claimed with an atomic conditional update (`UPDATE … SET paid_at = now() WHERE id = ? AND paid_at IS NULL`, and the same for `receipt_sent_at` / `notified_at`). Only the caller that wins the claim dispatches. |
| Concurrent events for one subject | `ProcessWebhookEvent` uses `WithoutOverlapping` keyed on the payment or subscription, so events for one subject are processed serially. `SyncPlan` is `ShouldBeUnique` per plan+gateway+mode. |
| Scheduled tasks | Select by state and re-check under the lock before acting, so an overlapping or re-run task is a no-op. |

### Settings (new `SettingsTab::Payments`)

New SettingKey cases:

- **General:** `PaymentsEnabled`, `PaymentsMode`, `Currency`, `PastDueGraceDays` (default 7)
- **Gateway toggles:** `StripeEnabled`, `PayPalEnabled`, `DemoGatewayEnabled`, `ManualPaymentsEnabled`
- **Stripe, per mode:** `Stripe{Sandbox,Live}{PublishableKey,SecretKey,WebhookSecret}`
- **PayPal, per mode:** `PayPal{Sandbox,Live}{ClientId,ClientSecret,WebhookId}`

- **Encryption.** A new exhaustive `SettingKey::isEncrypted()` match (beside
  `isSecret()`) marks the secret keys. `Settings` encrypts on write and
  decrypts on read. A value that fails to decrypt (APP_KEY rotated without
  `APP_PREVIOUS_KEYS`) reads as unset, is reported, and shows up as a
  Diagnostics error.
- **Write-only.** The Stripe and PayPal credentials are edited through modals
  (the same pattern as `MAILER_MODAL_KEYS`). Secrets are **never hydrated into
  the form or the Livewire snapshot**. The modal shows `sk_live_…a1b2 — set`,
  and a blank field means "keep". A test asserts no secret appears in the
  rendered HTML or the Livewire payload.
- **Password confirmation.** The credential modals require the current password
  (`current_password` rule) on submit.
- **Alerting.** A credential change sends `PaymentCredentialsChanged` to the ops
  alert address (queued, no link, like `PasswordChanged`) and is audited
  redacted through `isSecret()`.
- **Permission.** The Payments tab is visible only with `ManagePaymentSettings`.
- **Connect webhooks** (phase 3). This button creates the endpoint through the
  gateway API and stores the returned signing secret or webhook ID for the
  current mode.

### Dormancy

Switching payments off stops **new** payments, not existing ones:

- The pay page and demo checkout sit behind `EnsurePaymentsEnabled`, which
  404s them while `PaymentsEnabled` is off. They stay registered, because a DB
  setting cannot gate route registration under the route cache. This is the
  same pattern as `EnsureRegistrationIsEnabled`.
- The return, cancel and receipt routes stay up. They need a payment uuid plus
  a signature, so on an installation that never took a payment they lead
  nowhere, and an emailed receipt link keeps working after payments are
  switched off.
- The webhook route 404s for a gateway and mode with no signing secret, so an
  endpoint that was never set up looks absent. An endpoint that was set up
  keeps receiving, so refunds and holds in flight still complete. (Changed
  from "webhooks 404 while off" after the phase 1 code review.)

Admin resources return `false` from `canAccess()` while payments are off. The
Settings → Payments tab is always visible, so payments can be switched on.

Demo checkout routes are registered only when `DevLoginAccounts::enabled()`, so
they do not exist in production (see assumption 7).

### Routes

| Route | Purpose |
| --- | --- |
| `GET pay/{paymentLink:token}` | Livewire page: amount (if customer-entered), name, email, gateway choice, tax summary |
| `GET payments/{payment:uuid}/return` | Back from the gateway. Stripe: fetch and reconcile. PayPal: **capture the approved order** (or record the authorization), then reconcile. Idempotent with the webhook. |
| `GET payments/{payment:uuid}/cancelled` | Customer backed out; the payment stays pending until it expires |
| `GET payments/{payment:uuid}` (**signed**) | Receipt and status page for guests, like the drone demo's order page |
| `POST webhooks/{gateway}/{mode}` | CSRF-exempt, throttled, signature-verified. Mode in the path selects the secret, and events for both modes are accepted, so in-flight sandbox payments finish after a switch. |
| `GET pricing` | Public plan list (phase 2) |
| (Livewire action on `pricing`, verified account) | Start a subscription checkout (phase 2; a component action rather than a separate POST route) |
| `GET settings/billing` (auth, verified) | Livewire billing page (phase 2) |
| `GET demo-checkout/{payment:uuid}` | Demo gateway's fake hosted page (non-production only) |

`subscribed:{planKey}` middleware alias plus `User::subscribed(?string $planKey)`.

### Core flows

- **Checkout.** `StartCheckout` takes the Payable and asks it for the amount,
  which is computed server-side. For a customer-entered amount, the value is
  validated against min/max on the server. Then:
  1. TaxCalculator produces the breakdown.
  2. A `pending` Payment is created.
  3. The driver creates the hosted session: Stripe Checkout line items with tax
     as explicit lines; PayPal Orders v2 with `amount.breakdown.tax_total`.
     `intent`/`capture_method` follows the payable.
  4. The customer is redirected.

  Outgoing calls carry idempotency keys derived from the payment UUID and the
  operation.
- **Webhooks.** The controller verifies the signature synchronously (Stripe:
  HMAC via the SDK; PayPal: the `verify-webhook-signature` API with the stored
  webhook ID). It then does an `insertOrIgnore` of the WebhookEvent on
  (gateway, event_id), dispatches `ProcessWebhookEvent` (extends `App\Jobs\Job`)
  and returns 200. The job **re-fetches the subject from the gateway API** and
  reconciles against that authoritative state rather than the payload, so
  duplicate and out-of-order delivery are harmless. Unknown event types are
  marked `ignored`. Repeated signature failures raise a rate-limited ops alert.
- **Reconcile.** One `ReconcilePayment` action serves the return URL, the
  webhook and the Demo driver. It applies status transitions through
  `PaymentStatus`'s state machine; an illegal transition is logged and skipped,
  never applied. The work happens in one transaction under the row lock:
  append the ledger entries, recompute the projections, close a single-use
  link. The winner of the `paid_at` claim then dispatches `Payable::onPaid()`
  and the receipt after commit.
- **Refund.** `RefundPayment` follows the three-step pattern:
  1. Under `lockForUpdate`, check `amount ≤ captured − refunded − pending
     refunds` and create a pending Refund with its `idempotency_key`, then commit.
  2. Call the driver with that key.
  3. Apply the result under the lock (ledger entry, projections, status).

  Webhooks confirm the result idempotently. Refunds issued in a gateway
  dashboard arrive by webhook and are recorded with `initiated_by = null`. For
  Manual payments the refund is recorded only, in a single transaction.
- **Capture/void.** Stripe uses `capture_method=manual` on the PaymentIntent
  (partial `amount_to_capture` supported) and cancel to void. PayPal uses
  `intent=AUTHORIZE`, then captures or voids the authorization. Stripe holds
  last about 7 days; PayPal honours an authorization for 3 days, reauthorizable
  up to 29. A scheduled task alerts ops 24 hours before a hold expires and
  marks expired holds.
- **Manual.** A reusable Filament action "Record manual payment" (method:
  e-Transfer / cheque / cash / PayPal.Me / other, reference, date received) is
  available on payment links and on any payable's resource.

### Subscriptions (phase 2)

- **Plan sync.** Saving a Plan or PlanPrice dispatches `SyncPlan` for each
  enabled gateway in the current mode:
  - Stripe: Product plus a recurring Price, with tax rates synced as Stripe TaxRates.
  - PayPal: catalog Product plus a billing Plan, with a trial cycle, a
    `taxes.percentage` equal to the combined active rates, and
    `payment_failure_threshold`.
  
  A price change creates a new PlanPrice and new gateway price/plan. The old
  one is archived at the gateway (Stripe price `active=false`, PayPal plan
  deactivated), and existing subscriptions stay on it.
- **Subscribe.**
  - Stripe: Checkout in `mode=subscription` for the user's `billing_customers`
    row, with `trial_period_days` and `default_tax_rates`.
  - PayPal: create a subscription and redirect to its approve link.
  - Either way, the subscription is `incomplete` until the webhook or return
    reconciles it.
- **Renewals.** Stripe `invoice.paid` and PayPal `PAYMENT.SALE.COMPLETED` create
  a Payment row linked to the subscription, with the gateway's tax. The receipt
  email is sent and refunds work as usual.
- **Cancel at period end / resume.** Stripe uses `cancel_at_period_end` true or
  false. PayPal uses suspend plus scheduled cancel, and activate to resume (see
  assumption 8). Immediate cancel is admin-only.
- **Swap.**
  - Stripe: update the item price with `proration_behavior=create_prorations`.
  - PayPal: `revise`, which returns an approve link; the customer re-approves,
    and it takes effect next cycle with no proration.
  - The billing page explains which behaviour applies.
- **Failed renewals.** Stripe `invoice.payment_failed` and PayPal
  `BILLING.SUBSCRIPTION.PAYMENT.FAILED` set `past_due` and `past_due_since`,
  and send SubscriptionPaymentFailed to the customer (with a fix link) and ops. Access
  continues until `past_due_since + PastDueGraceDays`. A gateway cancellation
  ends access. Stripe's retry schedule is a dashboard setting that the API
  cannot set, so the README says to set it.
- **Access.** `subscribed()` is true for `trialing` and `active`, for
  `past_due` within the grace period, and for cancelled-but-`ends_at`-in-the-future.
- **Trial reminders.** A scheduled task sends TrialEnding 3 days out for both
  gateways. Stripe's `trial_will_end` event is ignored so both gateways behave
  the same.
- **Billing page** (`/settings/billing`). Shows current plan, status, renewal
  date, trial end and the grace-period warning, with actions for cancel/resume
  and swap. Lists the user's payments with receipt links. "Update payment
  method" opens a Stripe Billing Portal session for Stripe, or links to
  PayPal's autopay management for PayPal.
- **Demo driver.** Admin actions on a Demo subscription — "Simulate renewal"
  and "Simulate failed renewal" — go through the same reconcile code path.

### Admin (Filament, navigation group "Payments")

| Screen | Contents |
| --- | --- |
| Payments | List and view. Filters: status, gateway, mode (sandbox hidden by default). Actions: Refund (amount, reason, confirmation modal), Capture (amount), Void, Record manual payment. Refunds relation manager. Link to the gateway dashboard. |
| Payment links | CRUD, copy-URL action, payments relation, Record manual payment |
| Tax rates | CRUD; editing an in-use rate creates a new gateway TaxRate |
| Plans (phase 2) | CRUD with a prices relation, Sync action, sync status per gateway/mode |
| Subscriptions (phase 2) | List and view. Actions: cancel now, cancel at period end, resume. Demo simulation actions. |
| Disputes (phase 3) | Read-only, status badge, evidence deadline, dashboard link. Nav badge counts open disputes. |
| Webhook events | Read-only list and payload view, Retry action for `failed` |

Badge colours must be registered on the panel's `->colors()` (see `.ai/rules/filament.md`).

### Permissions

New `Permission` cases: `ViewPayments`, `RefundPayments`, `CapturePayments`,
`RecordManualPayments`, `ManagePaymentLinks`, `ManagePlans`,
`ManageSubscriptions`, `ManagePaymentSettings` (which also covers tax rates and
webhook events). Admin receives them through `Permission::cases()`. Policies
extend `BasePolicy` and are registered explicitly in `POLICIES`.

### Emails (all queued; each added to `PreviewableEmails`)

**Every payment email is queued.** They extend a new abstract
`App\Notifications\Payments\PaymentNotification`, which extends
`BaseNotification`, `implements ShouldQueue`, and sets `afterCommit = true` so
nothing is sent for a transaction that rolled back. This is the explicit per-class
opt-in that `.ai/rules/app-notifications.md` asks for. The existing unqueued
`QueueJobFailed` is unchanged, so a dead queue is still reported.

Each email is sent exactly once, guarded by the claims in
[Integrity](#integrity-transactions-immutability-idempotency). Payloads carry
model IDs, not snapshots, so a retried send renders current data.

- **Customer:**
  - PaymentReceipt, RefundIssued
  - SubscriptionStarted, SubscriptionCanceled, TrialEnding,
    SubscriptionPaymentFailed
  - SubscriptionRenewed is the receipt for every subscription payment, sent
    in place of PaymentReceipt (one receipt per payment, claimed the same way)

  Guests receive them through `Notification::route('mail', …)`.
- **Ops, to the ops alert address:**
  - PaymentCredentialsChanged (no link)
  - DisputeOpened
  - WebhookProcessingFailed
  - AuthorizationExpiring
  - DuplicatePaymentRefunded

### Scheduled tasks

Every task gets `withoutOverlapping(<explicit expiry>)` and `onOneServer()`,
and is added to `ScheduledTasksTest`.

| Command | Schedule | Job |
| --- | --- | --- |
| `payments:expire-checkouts` | hourly | Pending payments older than 24h → `expired` |
| `payments:check-authorizations` | hourly | Alert on holds expiring within 24h; mark expired holds |
| `payments:prune-webhook-events` | daily | Retention 90 days (config) |
| `payments:reconcile-stale` | every 15 min | Refunds, captures and payments stuck in a pending/processing state longer than 15 min are re-fetched from the gateway and finished (recovers from a crash between a gateway call and applying its result) |
| `payments:end-subscriptions` | hourly, phase 2 | PayPal period-end cancellations; grace-period expiry |
| `payments:notify-trials-ending` | daily, phase 2 | Trial reminders |

### Diagnostics (`ProductionDiagnostics`)

- **Errors:**
  - payments enabled with no gateway enabled
  - an enabled gateway missing credentials for the current mode
  - live mode with sandbox-looking Stripe keys (`sk_test_`)
  - an encrypted setting that won't decrypt
- **Warnings:**
  - sandbox mode in production
  - an enabled gateway with no webhook configured
  - a plan not synced to an enabled gateway
  - failed webhook events in the last 24h

### Security checklist

- The amount always comes from the server-side payable. The only customer input
  is a validated customer-entered amount.
- The return URL never marks a payment paid on its own; it reconciles against
  the gateway API.
- Receipts are behind signed URLs, and link tokens are unguessable.
- Webhooks are signature-verified, and mode-scoped secrets are selected by path.
- Named rate limiters cover pay-link submission (card-testing and
  session-creation spam) and webhooks.
- No floats anywhere in money. PayPal decimal strings are built from minor
  units using `Currency::decimals()`.
- Secrets are encrypted, write-only and redacted, and never appear in the
  Livewire payload or in logs. The Http client's logging must not include
  `Authorization` headers.

---

## Phase 1: Foundation + one-time payments (commit 1)

1. `composer require stripe/stripe-php` (approved).
2. Money, Currency, Gateway, GatewayMode, statuses, Capability; unit tests.
3. Settings: new keys, `isEncrypted()`, encrypt/decrypt in `Settings`,
   Payments tab, credential modals with password confirmation, alert
   notification.
4. `EnsurePaymentsEnabled`, `routes/payments.php`, CSRF exemption for
   `webhooks/*`, limiters.
5. TaxRate model, factory and resource; TaxCalculator with unit tests
   (13% HST, 5% + 7%, 9.975% QST, rounding edges, allocation).
6. Payable contract, `IsPayable`, Payment/Refund/PaymentTransaction/WebhookEvent
   models, migrations, factories, policies, permissions. Write-once field
   guards, delete guards, and the status transition tables.
7. Drivers: Stripe (plus the Laravel Http adapter), PayPal, Demo, Manual. One-time
   checkout, fetch, capture, void, refund, webhook verify.
8. Actions: StartCheckout, ReconcilePayment, RefundPayment, CapturePayment,
   VoidPayment, RecordManualPayment; webhook controller and job.
9. PaymentLink model and resource; public pay page, return, cancelled and receipt pages.
10. Admin: Payments, Payment links, Tax rates, Webhook events.
11. Emails: the `PaymentNotification` queued base, then PaymentReceipt,
    RefundIssued, PaymentCredentialsChanged, AuthorizationExpiring,
    WebhookProcessingFailed, DuplicatePaymentRefunded.
12. Scheduled tasks: expire-checkouts, check-authorizations,
    prune-webhook-events, reconcile-stale.
13. Diagnostics checks for phase 1.
14. README "Payments" section: enabling, credentials, webhooks, Demo gateway,
    upgrading PayPal Personal to Business.

**Tests** (Pest feature tests unless marked unit):

- Money / tax / state machine (unit)
- Dormancy: 404s and hidden nav while off
- Every permission, one allow and one deny per action
- Encrypted-at-rest, never-in-payload, a blank field keeps the value, password
  required, alert sent, audit redacted, decrypt failure
- Checkout on each gateway with `Http::fake()` + `Http::preventStrayRequests()`
- Return URL, including PayPal capture on return
- Webhook signature accept/reject, duplicate, out-of-order, unknown event,
  wrong mode
- Refunds: full, multiple partials, over-refund rejected, concurrent refunds,
  dashboard-initiated
- Capture: full and partial, void, hold expiry
- Payment links:
  - fixed and customer amounts, including out-of-range
  - single-use closure and double-pay auto-refund
  - expired and inactive links
- Manual payments and manual refunds
- Receipt signature required
- Emails: queued, sent to the right recipients, and not sent when the
  transaction rolls back
- Scheduled task guards
- **Integrity:**
  - the same webhook replayed 3× → one ledger entry, one receipt
  - return URL and webhook for the same capture → one ledger entry
  - pay form submitted twice → one payment, same checkout session
  - refund modal submitted twice → one refund
  - gateway call succeeds but its response is lost (fake throws after
    recording) → the retry sends the same idempotency key and records once
  - a crash between the gateway call and applying its result → `reconcile-stale`
    finishes it
  - updating a write-once field throws; deleting a payment, refund or ledger
    entry throws
  - a backwards status event is ignored
  - ledger sum equals the `amount_captured − amount_refunded` projection after
    every scenario
  - a gateway failure in step 2 leaves no half-written rows (the pending row is
    marked failed, never orphaned)

## Phase 2: Subscriptions (commit 2)

1. Plan, PlanPrice, Subscription and BillingCustomer models, migrations,
   factories, policies.
2. SubscriptionDriver on Stripe, PayPal and Demo; SyncPlan (plus tax-rate sync).
3. Actions: StartSubscription, ReconcileSubscription, Cancel, Resume, Swap;
   renewal payments; failed-renewal handling and grace period.
4. `User::subscribed()`, `subscribed:` middleware, pricing page, billing page, nav link.
5. Admin: Plans (with Sync), Subscriptions (with Demo simulation).
6. Emails: SubscriptionStarted, SubscriptionRenewed (the subscription
   receipt), SubscriptionCanceled, TrialEnding, SubscriptionPaymentFailed.
7. Scheduled tasks: end-subscriptions, notify-trials-ending.
8. Diagnostics: plan not synced. README subscriptions section.

**Tests:**

- Plan sync create and new-price-on-change per gateway
- Subscribe on each gateway
- The lifecycle via webhook fixtures: trialing → active → past_due → grace →
  canceled
- `subscribed()` for each status and grace boundary
- Cancel at period end and resume per gateway
- Swap (Stripe proration request; PayPal revise and re-approval)
- Renewal creates a Payment with gateway tax, and it can be refunded
- Trial reminder timing
- Billing page authorization (only your own subscription)
- The one-active-subscription rule, including two concurrent subscribe attempts
  (the row lock plus a unique active-subscription guard means only one wins)
- Integrity: replayed and out-of-order subscription webhooks, renewal payments
  recorded once, lifecycle emails sent once

## Phase 3: Hardening (commit 3)

1. **Disputes.** Model, webhooks (Stripe `charge.dispute.*`, PayPal
   `CUSTOMER.DISPUTE.*`), a read-only resource with a nav badge, and the
   DisputeOpened alert.
2. **Connect webhooks.** `RegisterWebhooks` for Stripe and PayPal, storing the
   secret or webhook ID per mode; re-registering replaces the old endpoint.
3. **Sandbox contract suite.** A `tests/Sandbox`, `Sandbox` testsuite in
   `phpunit.xml`, excluded from the default run and skipped unless
   `STRIPE_SANDBOX_SECRET` / `PAYPAL_SANDBOX_CLIENT_ID` and related variables
   are set. It covers:
   - Stripe: create a Checkout session; confirm a PaymentIntent server-side
     with `pm_card_visa`; partial and full refunds; manual capture and void;
     plan sync; subscription with a test clock where available; register and
     delete a webhook endpoint.
   - PayPal: order create and fetch; plan sync; subscription create; webhook
     create and verification; suspend/activate behaviour (assumption 8).

   It also re-captures the webhook fixture JSON so CI fixtures track real payloads.
4. **Browser smoke test.** Pay link → Demo checkout → receipt, in the existing
   `Browser` suite.
5. **Remaining Diagnostics checks.** Webhook missing, failed webhook events.
6. **Security review** of the whole payments surface (`/security-review`),
   with fixes applied.
7. **Final README pass**, including a Stripe/PayPal dashboard setup checklist.

---

## To verify against current docs during implementation

These are API details to confirm (with `search-docs` or the gateway docs), not
decisions:

- stripe-php's `HttpClient\ClientInterface` shape, and pinning the SDK's API
  version on the client and on webhook endpoints.
- Whether a Stripe Billing Portal configuration can be created through the API
  on first use, or must be set once in the dashboard.
- PayPal: `verify-webhook-signature` needing the webhook ID per mode;
  authorization honour and reauthorization windows; subscription
  suspend/activate billing-date behaviour; `revise` semantics.
- Stripe Checkout and PayPal Orders v2 both accepting explicit tax lines, so
  our calculated tax is charged to the cent.
