# Boilerplate

A Laravel 13 starting point with authentication, a Livewire + Flux UI frontend, and a
test suite wired for Test Impact Analysis.

## Stack

| Layer     | Choice                                                       |
| --------- | ------------------------------------------------------------ |
| Runtime   | PHP 8.5, Laravel 13                                          |
| Frontend  | Livewire 4, Flux UI 2, Tailwind CSS 4, Vite 8 (`vite-plus`)  |
| Auth      | Laravel Fortify (single login page)                           |
| Admin     | Filament 5 at `/admin`                                       |
| Testing   | Pest 5 with the Tia engine                                   |
| Static    | Larastan / PHPStan                                           |
| Style     | Laravel Pint                                                 |
| AI tools  | Laravel Boost, `.ai/rules`                                   |
| Deploy    | Docker (nginx + php-fpm + supervisor), Dokploy-ready          |

## Getting started

```bash
composer setup
```

That installs dependencies, creates `.env`, generates an app key, migrates, and
builds assets. Then run the dev server:

```bash
composer dev
```

## What's included

### Authentication

Fortify provides the backend; the UI is Livewire components under
`app/Livewire/Settings`. Enabled features in `config/fortify.php`:

- Registration and login
- Password reset and email verification
- Two-factor authentication (TOTP), with password confirmation
- Passkeys / WebAuthn, via Fortify's passkey support (`@laravel/passkeys`)

Fortify actions live in `app/Actions/Fortify`, with shared validation rules
extracted into `app/Concerns` (`PasswordValidationRules`,
`ProfileValidationRules`).

### Authorization

Roles and permissions are defined in code, not in database rows:
[`App\Auth\Role`](app/Auth/Role.php) and
[`App\Auth\Permission`](app/Auth/Permission.php). A user's `role` column is the
single source of truth; [`HasRoles`](app/Concerns/HasRoles.php) supplies
`hasRole()`, `hasPermission()`, `hasRoleAtLeast()` and a `withRole` scope.

```php
$user->hasPermission(Permission::ManageSettings);
$user->can(Permission::ManageSettings->value);   // through the Gate
$user->can('update', $otherUser);                // through UserPolicy
User::withRole(Role::Admin)->get();
```

| Role | Admin panel | Can do |
| --- | --- | --- |
| User | no | Their own account, billing and subscription |
| Editor | yes | Pages, media library, contact messages |
| Bookkeeper | yes | Read payments, refunds, subscriptions, disputes and tax rates |
| Manager | yes | Editor + Bookkeeper, plus refunds, capture/void, manual payments, payment links, cancelling subscriptions, and reading the user list |
| Administrator | yes | Everything, including settings, payment credentials, plans, users and roles, the audit trail and logs |

Staff roles land on the panel after signing in, and each sees only the screens
its permissions reach.

Check permissions rather than role names. `Role::Admin->permissions()` returns
`Permission::cases()` by enumeration, so a permission added later is granted to
administrators rather than silently denied.

Policies extend [`BasePolicy`](app/Policies/BasePolicy.php), which maps each of
the seven standard abilities to a permission and **denies any ability it has no
mapping for** — a new ability, or one somebody forgot to map, refuses rather
than allows.

[`AuthServiceProvider`](app/Providers/AuthServiceProvider.php) registers every
permission as a Gate ability and adds a `Gate::before` administrator bypass, so
a model whose policy has not been written yet is still manageable. The bypass
deliberately **skips** `delete`, `forceDelete` and `updateRole`: those are the
rules that deny an administrator on purpose — you cannot delete your own account
or demote yourself, which on a single-admin instance would lock the panel out of
itself. `Gate::before` short-circuits on any non-null return, so answering them
there would silently undo [`UserPolicy`](app/Policies/UserPolicy.php).

`$user->is_admin` still works: it is an accessor derived from the role, kept so
existing call sites did not have to change. New code should ask for a permission.

Adding a role is a case in the enum plus its grants in `permissions()` — no
migration, because the column is a string rather than a native enum (adding a
value to a MySQL/MariaDB enum column rewrites the table).

### Admin panel

Filament 5 serves an admin panel at `/admin`. Access is gated on the
`AccessAdminPanel` permission in two places: `User::canAccessPanel()` (Filament's
`FilamentUser` contract) and
[`FilamentAuthenticate`](app/Http/Middleware/FilamentAuthenticate.php), which
403s non-admins and sends guests to Fortify's login page.

**The dashboard** shows the business at a glance from real data
([`BusinessMetrics`](app/Payments/BusinessMetrics.php)): revenue this month
against the same point last month (net of refunds, read from the ledger), new
customers, active subscriptions and monthly recurring revenue, twelve months
of revenue, and a *Needs attention* list — disputes awaiting a response,
failed renewals, holds about to lapse, unanswered messages. Each widget and
item appears only to someone with the permission to act on it. Outside
production, `DemoBusinessSeeder` fills a fresh install with a year of demo
trading through the real payment actions (sandbox, Demo gateway), and the
dashboard carries the introduction to the author's work; production never
shows it.

**The business summary** (`app:send-business-summary`, checked hourly) emails
every administrator the same figures at 8am on Monday for the week just
ended — or on the 1st for the month — in the display timezone. It is on by
default and set under Settings → Email, skips a period with no activity, and
records each period sent in the settings table (not the cache, which every
container start clears), so a redeploy never sends it twice. `--force` sends
the last period now.

**The panel has no login page of its own.** `filament:install --panels`
scaffolds `->login()` in the panel provider, which would register a second login
at `/admin/login` — bypassing Fortify and with it 2FA, passkeys, and email
verification. That call is deliberately removed, so
`filament.admin.auth.login` does not exist and Fortify's `/login` is the only
way in. A test asserts the route stays absent.

`is_admin` is deliberately **not** mass-assignable; promote a user explicitly.

Filament **5** is required here rather than 4: Filament 4 depends on
`livewire/livewire ^3.5`, and this project is on Livewire 4.

### Dev login links

In every environment except `production`, the login page shows one-click login
buttons for the seeded accounts. This is a small first-party implementation rather than a
package — see [`config/dev-login.php`](config/dev-login.php).

`APP_ENV` is the only gate, and it is a **denylist**: `production` is the sole
blocked environment, so a bespoke name (`staging`, `demo`, `review-42`) gets the
buttons without being registered first. The route itself is only registered when
[`DevLoginAccounts::enabled()`](app/Auth/DevLoginAccounts.php) allows the current
environment, so in `production` `POST /dev-login` does not exist at all rather
than relying on a runtime guard. The check fails closed if the list is empty.

> Because the seeded credentials are fixed and public, **any deployed instance
> that is not `production` allows passwordless login to them**. That is a
> deliberate trade for demo instances — deploy anything real as `production`.

The form submits a **position** in the configured account list, never an email
address, so a crafted request cannot log into an account the application did not
offer, and no `redirect_url` is read from user input.

[`DevLoginController`](app/Http/Controllers/DevLoginController.php) and Fortify's
`LoginResponse` share the
[`ResolvesLoginRedirect`](app/Http/Responses/ResolvesLoginRedirect.php) trait, so
a dev login lands exactly where a real login would: the intended URL if one was
captured, else the admin panel for admins and the dashboard for everyone else.

### Seeding

```bash
php artisan migrate:fresh --seed
```

Demo accounts with **fixed credentials** — the admin's in
[`config/first.php`](config/first.php), the rest (with their roles) in
[`config/dev-login.php`](config/dev-login.php) — no environment
variables, so a fresh clone and a fresh deployment both come up usable with
nothing to configure:

| Account | Email | Password | Access |
| --- | --- | --- | --- |
| Admin | `admin@example.com` | `password` | Filament panel at `/admin` |
| User | `test@example.com` | `password` | `/dashboard` only |
| Editor | `editor@example.com` | `password` | Panel: pages, media, contact messages |
| Bookkeeper | `bookkeeper@example.com` | `password` | Panel: payments, subscriptions, disputes, tax rates (read-only) |
| Manager | `manager@example.com` | `password` | Panel: editor + bookkeeper, plus refunds, holds, payment links, subscriptions, users (read-only) |

`AdminUserSeeder` is idempotent: an existing account is promoted to admin but
its name and password are left alone, so a password you change is never reset by
a redeploy. `DatabaseSeeder` adds the other accounts wherever the quick logins
are offered — every environment except `production`.

`SettingsSeeder` adds placeholder business contact details (address, phone,
email) so the public footer and the settings form have something to show. It
seeds only keys with no row yet, so details you edit from **Admin → Settings**
are never overwritten by a redeploy.

> **The credentials are public.** Change the admin password from its settings
> page once a deployed instance is reachable. Deploy real instances as
> `APP_ENV=production` (the default), which is the only environment that
> withholds the passwordless [dev login](#dev-login-links).

### Deployment

The app ships as a single image built from a four-stage
[`Dockerfile`](Dockerfile) — a shared `php-base` carrying the extension set,
then composer vendor, then assets, then runtime — which
runs three roles selected by `CONTAINER_ROLE`: the web tier (nginx + php-fpm),
a queue worker (`queue:work`), and the scheduler (`schedule:work`). See
[`docker/README.md`](docker/README.md) for the full walkthrough.

```bash
cp .env.docker.example .env.docker   # first time only
docker compose --env-file .env.docker up --build -d
open http://localhost:8011
```

`--env-file .env.docker` is required: Compose otherwise falls back to the
project-root `.env` — the application's own Laravel env — and silently builds
the throwaway stack from it. A `DOCKER_LOCAL_STACK` tripwire makes that failure
loud instead of silent.

- [`docker-compose.yml`](docker-compose.yml) — local only. Embeds a throwaway
  `APP_KEY`, a known database password, and a published port; never deploy it.
- [`docker-compose.dokploy.yml`](docker-compose.dokploy.yml) — the deploy stack.
  No database service, no published ports, and every secret comes from the
  environment. Required variables use `${VAR:?message}`, so a missing one fails
  the deploy with a named error rather than booting on a silent default.

The entrypoint waits for the database, migrates, seeds, rebuilds caches against
the real environment, and republishes Filament's assets on every boot.

Client prototypes are `demo/<slug>` branches rather than clones:
`./docker/new-demo.sh <slug>` creates one, CI publishes it as the image tag
`demo-<slug>`, and a reusable Dokploy slot is pointed at it. See
[Client demos](docker/README.md#client-demos-demo-branches).

#### Overriding APP_ENV for a staging instance

`APP_ENV` defaults to `production` but is overridable in Dokploy. Note what the
value controls before changing it:

| `APP_ENV` | `migrate:fresh` / `db:wipe` | Password policy | Passwordless dev login | Demo user seeded |
| --- | --- | --- | --- | --- |
| `production` | prohibited | strict | **no** | no |
| `staging` (or any other) | prohibited | strict | **yes** | yes |
| `local` / `testing` | **allowed** | **none** | **yes** | yes |

**`production` is the only value that withholds the passwordless
[dev login](#dev-login-links).** Since the seeded credentials are fixed and
public, a deployed instance on any other `APP_ENV` can be signed into by anyone
who reaches its login page. Deploy anything that is not a throwaway demo as
`production`, which is the default.

`staging` remains the right choice for a deployed **demo** that is meant to be
walked into: it keeps the destructive-command and password guards on while still
offering the one-click logins. It is not a way to get a "safer production".

**Never set `local` on a deployed instance** — on top of the dev login it
re-enables `migrate:fresh` and `db:wipe` against that database and drops the
password policy entirely.

#### Trusted proxies

[`config/trustedproxy.php`](config/trustedproxy.php) supplies the key
`Illuminate\Http\Middleware\TrustProxies` reads. It matters behind a
TLS-terminating reverse proxy: Traefik forwards plain HTTP with an
`X-Forwarded-Proto: https` header, and if that proxy is untrusted Laravel reads
the request as insecure and generates `http://` URLs on an `https://` page —
which browsers block as mixed content, taking the stylesheet and JS bundle with
them.

`TRUST_PROXIES=*` is set in the Dokploy stack and is safe there precisely
because nothing is published: only Traefik can reach the container. Leave it
unset on a same-host nginx → php-fpm deploy, where trusting those headers from
any client would let it spoof both scheme and IP. A comma-separated list names
specific proxies instead.

#### Checking a deployment's configuration

[`docker-compose.dokploy.yml`](docker-compose.dokploy.yml) is the reference for
production values — it pins `APP_DEBUG=false`, `SESSION_ENCRYPT`,
`SESSION_SECURE_COOKIE` and the rest, and fails the stack outright when a
required secret is missing. There is deliberately no `.env.production.example`
beside it; a second copy of those values would only drift.

What the compose file cannot tell you is what a *running* instance actually
resolved. **Admin → Settings → Diagnostics** audits the live configuration and
reports what is wrong: debug mode, a missing application key, SQLite in
production, unencrypted sessions or an insecure session cookie are errors; a
wildcard proxy list, a container-local image disk, the log mailer, a fallback
passkey secret and debug-level logging are warnings, since each is defensible in
some deployments.

The checks live in [`App\Settings\ProductionDiagnostics`](app/Settings/ProductionDiagnostics.php)
and read resolved config rather than the env file, so a value baked in by
`config:cache` is reported as it actually is.

### Settings

Livewire components for profile updates, security (2FA, passkeys), appearance,
and account deletion, routed from `routes/settings.php`.

### API

A deliberately thin, versioned skeleton — enough that adding the first endpoint
is writing a route, not wiring a subsystem.

- [`routes/api/v1.php`](routes/api/v1.php), registered from
  [`bootstrap/app.php`](bootstrap/app.php) under the `api` middleware group with
  the `api/v1` prefix and `api.v1.` name prefix. The version is in the filename,
  so a v2 is a new file beside it rather than a rewrite.
- [`BaseApiResource`](app/Http/Resources/BaseApiResource.php) pins the `data`
  envelope per-resource instead of relying on the global, mutable default, with
  [`UserResource`](app/Http/Resources/UserResource.php) as the worked example —
  fields listed explicitly, so a column added later is never published by
  accident, and `avatar_url` opt-in via `->withAvatarUrl()` because resolving it
  stats the images disk once per user.
- JSON error shapes come from `shouldRenderJsonWhen` in `bootstrap/app.php`;
  `ApiSkeletonTest` pins that a 404 and a 422 under `api/*` stay JSON even when
  the client asks for HTML.

Sanctum is **not** installed. Token auth is a ten-minute addition when a project
needs it, and an unused authentication surface until then; `routes/api/v1.php`
carries a commented `auth:sanctum` group showing where it goes.

### Payments

Built in and **off until switched on** (Admin → Settings → Payments). While off,
every payment route answers 404 and the payment screens are hidden, so an
installation that never takes money looks like one without the module. The code
lives in [`app/Payments`](app/Payments).

| Gateway | What it is |
| --- | --- |
| Stripe | Stripe Checkout (hosted), for Canadian or US accounts. Cards, Apple Pay, Google Pay. |
| PayPal | PayPal Checkout (Orders v2). Needs a **Business** account: PayPal only issues live API credentials to Business accounts (upgrading a Personal one is free and keeps its login). |
| Demo | A pretend gateway with no credentials and no money, for client demos and tests. Refused in `production`, like the dev login. |
| Manual | Money received outside the site (Interac e-Transfer, cheque, cash, PayPal.Me), recorded by an administrator. |

Customers pay on the gateway's hosted page, so card data never touches this
application. One-time payments, full and partial refunds, and holds
(authorize now, capture or void later) work on every gateway.

**What gets paid for.** Anything implementing
[`Payable`](app/Payments/Contracts/Payable.php) -- the model computes the
amount, so a price never comes from the browser. The boilerplate ships
[`PaymentLink`](app/Models/PaymentLink.php) as its own payable (Admin →
Payments → Payment links): a `/pay/{token}` page for a fixed or
customer-entered amount, single-use (an invoice) or reusable. A project's own
`Order` or `Booking` becomes payable by implementing the contract with
`App\Concerns\IsPayable`, and gets the admin actions and receipts for free;
[`RecordManualPaymentAction`](app/Filament/Actions/RecordManualPaymentAction.php)
adds manual payments to its resource.

**Tax.** Admin → Payments → Tax rates. Every active rate is added on top of a
taxable item's price, each rounded on its own (so a 5% and a 7% tax, such as
GST + PST or state + city sales tax, print as two lines on the receipt). The
lines charged are snapshotted onto the payment, so editing a rate never
changes a past receipt. Each rate takes an optional **registration number**
(a GST/HST, QST or VAT number), printed beside its line on receipts.

**Receipt numbers.** Every payment is numbered `R-000123` when it is first
seen paid, in the same transaction, from a locked counter in the `sequences`
table — so the series is gap-free (a rolled-back payment hands its number
back) and never shared. Sandbox and live count separately. The prefix is
`payments.receipt_prefix`; the UUID stays the internal reference.

**PDF receipts.** Rendered on demand with dompdf
([`ReceiptPdf`](app/Payments/ReceiptPdf.php)): downloadable from the receipt
page, the customer's billing page and the admin payment view, and attached to
receipt and renewal emails. They carry the business details and logo from
Settings, the receipt number and each tax's registration number. Sandbox
receipts are stamped as test payments and named `…-test.pdf`. Paper size is
`payments.receipt_paper` (`letter` or `a4`).

**Exports.** Payments (paid ones, with a column per tax and a *Paid between*
filter), refunds (succeeded, with a date range) and users export to CSV or
Excel through Filament's exporter, from the Payments and Users screens, for
anyone who can view that list. The file is built on the queue and its
download link arrives in the panel's notification bell. Dates are ISO in the
business timezone, amounts plain decimals, and customer-typed text is guarded
against spreadsheet formula injection. `app:prune-exports` deletes exports,
their files and their notifications after a week.

**Credentials** are entered in the panel, one set for sandbox and one for
live, and are encrypted with `APP_KEY`, never sent back to the browser, and
changed only with the administrator's password; every change emails the ops
alert address. **Rotating `APP_KEY` makes them unreadable** unless the old key
is listed in `APP_PREVIOUS_KEYS` -- Diagnostics reports it if that happens.

**Webhooks.** Settings → Payments → **Connect webhooks** registers this site's
endpoint at Stripe or PayPal for the current mode, subscribed to every event
the module uses, and stores the signing secret (Stripe) or webhook ID
(PayPal); run it again after an upgrade to refresh the event list. It needs
the site on a public HTTPS address (`APP_URL`). Locally, forward events with
`stripe listen --forward-to <APP_URL>/webhooks/stripe/sandbox` and paste the
secret it prints into the Stripe credentials. The endpoints are
`/webhooks/{stripe|paypal}/{sandbox|live}`. Payments still complete without
webhooks -- the customer's return and the scheduled reconciliation record
them -- but dashboard refunds, disputes, renewals and customers who close the
tab after paying are only picked up promptly with them.

**Disputes.** Chargebacks and PayPal claims arrive by webhook and are listed
under Payments → Disputes (the badge counts those awaiting a response), with an
email to the ops address when one opens. Evidence is submitted in the
gateway's dashboard, linked from each dispute. A dispute is tracked beside its
payment, not entered on the payment's ledger: the payment was not refunded, and
the money may come back if the dispute is won. A payment with an open or lost
dispute cannot be refunded as well -- that would pay the customer twice.

**Integrity.** Money is integer minor units throughout. Every movement is an
append-only row in `payment_transactions`; a payment's amounts, currency and
customer are write-once and payments are never deleted, by the panel or by
code. Every gateway call carries an idempotency key, webhook events are stored
once per id, and a payment is re-read from the gateway rather than trusted from
a webhook payload, so retries, duplicate deliveries and out-of-order events are
harmless. State changes run in short transactions under a row lock that is
never held across a gateway call; the scheduled tasks (`payments:*` in
[`routes/console.php`](routes/console.php)) expire abandoned checkouts, warn
about holds nearing expiry and finish any operation whose result was lost.

Emails (receipts, refunds, subscription notices, operator alerts) are queued,
sent only after the change commits, and previewable at `/dev/mails`.

#### Subscriptions

Plans are defined in Admin → Payments → Plans and **synced out** to Stripe
(products and prices) and PayPal (products and billing plans) automatically
when saved; the Sync button reports what a gateway refused. A plan has a key
that code checks access by, an optional free trial and a taxable flag, and one
or more prices (monthly, yearly, every N months). A price is never edited:
add a new one and retire the old, and existing subscribers stay on theirs.

Customers compare plans at `/pricing` and subscribe with a verified account;
they manage their subscription at `/settings/billing` (cancel at period end
and take it back, change plan, update the card, see receipts). Each user has at
most one live subscription. Gate features with `$user->subscribed()` /
`$user->subscribed('pro')`, or the `subscribed` / `subscribed:pro` route
middleware.

- **Renewals** are billed by the gateway and recorded as ordinary payments,
  with a receipt each, refundable like any other.
- **Tax on subscriptions is charged by the gateway**, from copies of the
  configured rates (Stripe TaxRates; one combined percentage on the PayPal
  billing plan). A rate change applies to new subscriptions; existing ones keep
  the tax they started with.
- **Failed renewals**: the gateway retries; the subscriber and the ops address
  are emailed once, and the subscriber keeps access for the grace period
  (Settings → Payments, default 7 days). Stripe's retry schedule is set in the
  Stripe dashboard (Billing → Revenue recovery), not through the API.
- **PayPal** has no cancel-at-period-end: the subscription is suspended and
  `payments:end-subscriptions` cancels it when the period runs out. A PayPal
  plan change needs the customer's approval at PayPal and applies from the next
  cycle; Stripe's applies at once, prorated.
- **Stripe's billing portal** (used for "Update payment method") must be saved
  once in the Stripe dashboard (Settings → Billing → Customer portal) for each
  mode before it can be opened.
- **Demo** subscriptions renew only when an administrator presses "Simulate
  renewal" (or "Simulate failed renewal") on the subscription.

Subscriptions use the same webhook endpoints as payments; **Connect webhooks**
subscribes them to the subscription events too.

#### Going live: gateway checklist

Stripe (dashboard, for each of test and live mode):

1. Developers → API keys: copy the secret key into Settings → Payments →
   Stripe credentials. A restricted key needs write access to Checkout
   Sessions, PaymentIntents, Refunds, Customers, Products, Prices, Tax Rates,
   Subscriptions, Billing Portal sessions and Webhook Endpoints, and read
   access to Invoices and Disputes.
2. Connect webhooks from the settings tab (or add an endpoint by hand and
   paste its signing secret).
3. Settings → Billing → Customer portal: save it once, so "Update payment
   method" can open it.
4. Billing → Revenue recovery: set the retry schedule for failed renewals, and
   what happens after the last retry (cancel the subscription).
5. Business settings → Public details: the name customers see on Checkout and
   card statements.

PayPal (developer dashboard, sandbox and live apps):

1. A **Business** account; Apps & Credentials → create an app and copy its
   client ID and secret into the PayPal credentials.
2. Connect webhooks from the settings tab (or add a webhook to the app by
   hand, subscribed to the events in `PayPalDriver::WEBHOOK_EVENTS`, and paste
   its ID).
3. Account settings → Payment preferences: the business name shown on
   PayPal's pages.

Then switch Settings → Payments → Mode to Live, and check Diagnostics.

#### Testing against the real sandboxes

The feature suite fakes both gateways. `tests/Sandbox` calls the real Stripe
test mode and PayPal sandbox instead, to catch an API change the fakes cannot:

```bash
STRIPE_SANDBOX_SECRET=sk_test_... \
PAYPAL_SANDBOX_CLIENT_ID=... PAYPAL_SANDBOX_CLIENT_SECRET=... \
vendor/bin/pest --testsuite=Sandbox
```

It is never part of the default run, each gateway's tests skip without its
credentials, and a Stripe key must be a test key. It tidies up what it creates
(archiving products and prices, cancelling subscriptions, deleting webhook
endpoints) and saves the real payloads it read to
`storage/framework/testing/payment-captures/` for comparison with the fixtures
in `tests/Fixtures/Payments`.

What needs a person clicking through PayPal's own pages is checked by hand in
the PayPal sandbox, with a sandbox buyer account, before relying on PayPal:

- pay a payment link, and one for a held (authorized) payment, then capture
  and void it from the admin panel;
- subscribe, then cancel at period end (the subscription shows as suspended at
  PayPal) and keep it, confirming it re-activates without an extra charge;
- change plan and approve the change at PayPal;
- refund a subscription payment from the admin panel, and one from PayPal's
  own dashboard (it should appear here within a minute).

### Error pages

`resources/views/errors/` styles 403, 404, 419, 429, 500 and 503 with the
application's own design system, through
[`x-errors.layout`](resources/views/components/errors/layout.blade.php). 419 is
the one users actually hit: leaving a form or a Livewire page open past the
session lifetime expires the CSRF token, and Laravel's default "Page Expired"
reads as a fault rather than as "log in again and retry".

These pages deliberately do **not** extend `x-layouts::app` or include
`partials.head`. A 500 is most often a database outage, and the
`View::composer('*')` that supplies `$businessName` reads the settings table —
inheriting it means the error view throws while rendering and the user gets
Laravel's unstyled fallback at exactly the moment these templates exist for. So
they brand from `config('app.name')`, the one intentional exception to the rule
that views read `$businessName`. `ErrorPagesTest` asserts each page still
renders against an unreachable database.

### Tests

The feature suite covers each auth flow — authentication, registration,
password reset, password confirmation, email verification, the 2FA challenge —
plus settings, the dashboard, and the admin/dev-login behaviour described above:

- `AdminPanelAccessTest` — the Filament login route stays absent, guests redirect
  to Fortify, non-admins get 403, admins get in, `is_admin` resists mass
  assignment, and each role lands on the right page after login.
- `AuthorizationTest` / `RoleTest` — role defaults and mass-assignment
  protection, permission-to-gate registration, the administrator bypass, and
  that the bypass does not override the self-deletion and self-demotion rules.
- `ErrorPagesTest` — each status code resolves to this application's template
  rather than the framework's, every page is `noindex`, and all of them render
  with the database unreachable.
- `AdminUserSeederTest` — seeding, idempotency, and the production guard.
- `DevLoginTest` — the buttons render and sign in the right account in `local`,
  an unoffered position or unseeded account 404s, a submitted email or
  `redirect_url` is ignored, and disallowed hosts and environments are refused.
- `TrustedProxiesTest` — the config key exists, `TRUST_PROXIES` parses into the
  shape the middleware expects, and asset URLs come out `https` behind a trusted
  proxy and `http` without one.

```bash
composer test        # config clear, Pint check, PHPStan, then the suite (parallel)
vendor/bin/pest      # the suite alone
```

#### Browser tests

A handful of smoke tests in [`tests/Browser`](tests/Browser) drive a real
browser through [Pest's browser plugin](https://pestphp.com/docs/browser-testing),
covering what a server-side assertion cannot see: a page that returns 200 while
throwing in the browser. They cover login, a rejected password, the 2FA
challenge screen, and that the admin panel and its settings tabs render.

```bash
npx playwright install chromium         # once
vendor/bin/pest --testsuite=Browser
```

They run as their own CI job so a browser download and the occasional browser
flake stay out of the fast feedback loop. Two limits are worth knowing before
adding more, both recorded in [`.ai/rules/browser.md`](.ai/rules/browser.md):
**file uploads do not work** (the plugin's test server discards uploaded files,
so Livewire uploads fail there while working in a real browser), and **WebAuthn
cannot be driven** (no CDP session, so no virtual authenticator). Passkey
coverage therefore stops at the server's challenge payload, in
`PasskeyLoginOptionsTest`.

## Test Impact Analysis (Tia)

Configured in [`tests/Pest.php`](tests/Pest.php). Tia records which tests touch
which files, then re-runs only the tests your latest changes affect and replays
cached results for the rest. It requires the PCOV coverage driver.

```php
pest()->tia()->defaultBranch('main')->locally();
```

**`locally()`** activates Tia on every local `pest` / `artisan test` run, with no
`--tia` flag needed.

**`defaultBranch('main')`** names the baseline branch that every other branch
falls back to reading. Without it, Tia fails on any checkout that has a remote
but no `origin/HEAD`:

```
ERROR  Tia mode could not determine the default branch.
```

The alternative is `git remote set-head origin --auto`, but that writes into
`.git/` and is therefore per-clone and uncommitted — every teammate, fresh
clone, and CI runner would hit the same error. Naming the branch in config fixes
it once for everyone, and is deterministic in CI where the checkout is often
shallow or detached with no `origin/HEAD` at all.

The tradeoff: renaming the default branch means updating this line too. That
failure is loud and immediate, unlike the silent full-suite re-runs the error
warns about.

### Tia in CI

Pest's environment defaults to LOCAL and only becomes CI when `--ci` is passed
explicitly. Pass it so the pipeline runs the full suite rather than trusting a
cached dependency graph. Because `artisan test` does not forward `--ci`, CI
should invoke Pest directly:

```bash
vendor/bin/pest --ci
```

## Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push to
`main` and every pull request:

- **Pint & PHPStan** — `pint --test` and `phpstan analyse` (level 8).
- **Pest** — the suite on sqlite, in parallel.
- **Pest (mysql / mariadb)** — the same suite against `mysql:8.4` and
  `mariadb:11`.

That last job exists because production runs MySQL or MariaDB (the Dockerfile
installs `pdo_mysql`) while the fast job runs sqlite, which tolerates looser
typing and different strict-mode and DDL behaviour. A green sqlite suite is not
evidence the deployed database agrees.

The DB settings are passed as job environment variables, which works because
PHPUnit's `<env>` elements do not overwrite a variable already set in the
process environment. `DB_URL` is blanked for the same reason: `phpunit.xml` sets
it empty, and a non-empty `DB_URL` would take precedence over host and port.

[`.github/dependabot.yml`](.github/dependabot.yml) tracks Composer, npm, GitHub
Actions and the Dockerfile's pinned base images, grouping the first-party
Laravel packages so they land as a set.

## Code style and static analysis

```bash
composer lint         # Pint, fixing in place
composer lint:check   # Pint, check only
composer types:check  # PHPStan
```

Pint runs against [`pint.json`](pint.json). Blade and Tailwind formatting is
handled by Prettier with `prettier-plugin-blade` and
`prettier-plugin-tailwindcss`.

## AI agent configuration

[`CLAUDE.md`](CLAUDE.md) carries the Laravel Boost guidelines — conventions,
Artisan and Pint usage, and testing expectations for agents working in this
repo.

Durable project-specific rules live in [`.ai/rules`](.ai/rules/), grouped by area
and mapped to file globs in [`.ai/rules/index.md`](.ai/rules/index.md). Agents
read the rule files matching the paths they are about to touch.

- [`filament.md`](.ai/rules/filament.md) — why the admin panel must never enable
  Filament's own login page, and what to re-check after regenerating a panel.

Record new rules with Boost's `record-rule` tool rather than editing these files
by hand, so they are committed and shared with the team rather than scoped to one
session.
