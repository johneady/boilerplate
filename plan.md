# Plan: Gradient Mesh Avatar Fallback

Replace the flat-blue initials fallback with a deterministic two-hue gradient,
derived from a stable hash of the user, with the initials overlaid in white.

Applies when `avatar_path` is null or the processed conversions are not yet on
disk — i.e. exactly where `User::avatarUrl()` returns null today.

---

## Constraints discovered during investigation

These are load-bearing. Read before writing code.

### 1. `<flux:avatar>` cannot render a gradient through a prop
`vendor/livewire/flux/stubs/resources/views/flux/avatar/index.blade.php` builds
its background from a `match($color)` over fixed Tailwind classes
(`bg-red-200 text-red-800`, …). There is no gradient path and no background
slot. `color="auto"` only picks one of 17 flat colours by `crc32` hash.

Consequence: the gradient must be passed as an inline `style` attribute, which
Flux merges onto the root element via `$attributes`. The flat zinc fallback
(`[:where(&)]:bg-zinc-200`) is wrapped in `[:where(&)]` — zero specificity — so
an inline style overrides it cleanly without `!important`.

The `text-zinc-800 dark:text-white` is also `[:where(&)]`-wrapped, so initials
colour needs an explicit `text-white` class to stay legible on a saturated
gradient in light mode.

### 2. The avatar renders in FIVE places, not one
Per `.ai/rules/views-components.md`. Miss one and it silently keeps the old
look while the others are correct:

| # | File | Component | Prop |
|---|---|---|---|
| 1 | `resources/views/components/desktop-user-menu.blade.php:7` | `flux:sidebar.profile` | `:avatar` |
| 2 | `resources/views/components/desktop-user-menu.blade.php:19` | `flux:avatar` | `:src` |
| 3 | `resources/views/layouts/app/sidebar.blade.php:51` | `flux:profile` | `:avatar` |
| 4 | `resources/views/layouts/app/sidebar.blade.php:63` | `flux:avatar` | `:src` |
| 5 | `resources/views/partials/settings/profile.blade.php:3` | `flux:avatar` (`size="xl"`) | `:src` |

Note the prop name differs between `profile`-family and plain `avatar`.

Open question to verify during implementation: `flux:sidebar.profile` and
`flux:profile` are wrappers — confirm they forward arbitrary `style`
attributes down to the inner avatar rather than onto the button. If they do
not, those two sites need the gradient applied differently (see Step 4).

### 3. Filament is a separate, non-Flux path
`app/Filament/Resources/Users/UserResource.php:147` builds a base64 data-URI
SVG (`initialsAvatarUrl()`) with hardcoded `#dbeafe` / `#1d4ed8`. It is used as
`->defaultImageUrl()` on an `ImageColumn`, so it *must* stay an image URL —
CSS is not an option there. The gradient becomes an SVG `<linearGradient>`.

This is why the hash function must live in one shared place: a user who is
teal in the sidebar and orange in the admin table is a bug.

### 4. Do not introduce a remote service
The comment at `UserResource.php:143-146` records a settled decision: no
third-party initials service, because the admin panel lists every user and a
remote URL would leak the whole user table on each page view. The gradient is
generated locally in both paths.

### 5. Untrusted input
Initials derive from the user-supplied `name`. The existing SVG escapes them
with `e()` — a name starting with `<` would otherwise break out of `<text>`.
Keep that. The Blade path is escaped by `{{ }}` already.

---

## Step 1 — Shared gradient source of truth on `User`

In `app/Models/User.php`, beside `initials()`:

```php
/**
 * Deterministic gradient for this user's initials avatar.
 *
 * Seeded from the immutable primary key rather than name or email so a user
 * who corrects a typo in their name keeps the avatar colour their colleagues
 * recognise. Returns two hex stops, light-to-dark, for the SVG and CSS paths
 * alike -- Filament renders an <img>, the Flux sites an inline style, and a
 * user must not be teal in one and orange in the other.
 *
 * @return array{from: string, to: string}
 */
public function avatarGradient(): array
```

Implementation notes:
- Seed: `crc32((string) $this->getKey())`. Falls back to `$this->name` when the
  key is null (unsaved model in a test).
- Map the hash onto a fixed palette of hue pairs — a `const` array of
  `['from' => '#…', 'to' => '#…']` entries, ~8-12 pairs. Fixed hex, not
  computed HSL: predictable, reviewable, and matches how the codebase already
  writes colours.
- Add a note that reordering the palette changes every existing user's colour
  (same warning Flux itself carries), so entries get appended, never reordered.
- Pick pairs with enough depth that white initials pass contrast in both
  themes; avoid pale stops.

Add `avatarGradient` to the class docblock if the file lists methods.

## Step 2 — CSS helper for the Flux sites

Add a second small accessor so Blade stays readable and the gradient string is
built once:

```php
/**
 * Inline background for the initials fallback on the Flux avatar components.
 *
 * Flux builds its avatar background from fixed Tailwind classes with no
 * gradient path, so this is applied as a style attribute. Its flat zinc
 * default is wrapped in [:where(&)] -- zero specificity -- so the inline
 * style wins without !important.
 */
public function avatarGradientStyle(): string
```

Returns `background-image:linear-gradient(135deg,#from,#to)`.

## Step 3 — Filament SVG

Rewrite `UserResource::initialsAvatarUrl()` to emit a `<linearGradient>` in
`<defs>` and fill the rect with it. Keep:
- the `e()` escaping of initials,
- the existing docblock explaining why it is generated locally,
- the 64×64 viewBox and font sizing.

Change text fill to white. Gradient id should be unique-ish per user
(`grad-{$user->getKey()}`) — several of these render in one table page and
duplicate DOM ids across data-URIs are separate documents, but a unique id
costs nothing and avoids surprises if the SVG is ever inlined.

## Step 4 — Wire up the five Blade sites

For each of the five, add the gradient style and white text **conditionally** —
only when there is no image, otherwise a processed avatar gets a pointless
inline style behind an opaque `<img>`.

Pattern (plain `flux:avatar` sites):

```blade
@php($avatarUrl = auth()->user()->avatarUrl())
<flux:avatar
    circle
    :src="$avatarUrl"
    :name="auth()->user()->name"
    :initials="auth()->user()->initials()"
    :style="$avatarUrl ? null : auth()->user()->avatarGradientStyle()"
    :class="$avatarUrl ? null : 'text-white'"
/>
```

Keep `circle` on all five — the rule file notes the conversions are square and
the settings preview is the fifth circle.

For sites 1 and 3 (`flux:sidebar.profile` / `flux:profile`): verify attribute
forwarding first. If `style` lands on the button rather than the avatar,
prefer passing the gradient through whatever avatar-specific attribute the
wrapper exposes (Flux uses `avatar:` prefixed attributes for this, e.g.
`avatar:class`); check the component stub before guessing.

`resources/views/partials/settings/profile.blade.php` already resolves
`$this->avatarUrl` — reuse that, don't call the model again.

## Step 5 — Tests

Per `.ai/rules/views-components.md`, asserting a gradient "appears in the
dashboard response" is a FALSE PASS: four renders exist, so one correct render
satisfies it. Count instead.

Extend `tests/Feature/DashboardTest.php`:

- **Fallback shows the gradient in all four menu renders** — user with
  `avatar_path => null`; assert
  `substr_count($html, $user->avatarGradientStyle())` is `4`.
- **Uploaded avatar suppresses the gradient** — user with processed
  conversions on the fake disk; assert the gradient string is absent, and keep
  the existing count of 4 on `src="/storage/…"`.
- **Colour is stable across renders** — two requests for the same user yield
  the same gradient; two different users yield different gradients (pick ids
  that land on different palette entries, or assert over a spread of users
  that more than one distinct gradient appears).

Add to `tests/Feature/UserResourceTest.php`:
- `initialsAvatarUrl()` embeds the same two hex stops as `avatarGradient()` for
  the same user — this is the test that actually catches the two paths drifting
  apart, and is the most valuable one here.
- A name beginning with `<` is still escaped in the decoded SVG.

Unit-test `avatarGradient()` directly for determinism and palette bounds.

Settings page: `tests/Feature/Settings/` — assert the fifth render falls back
too, since Dashboard tests do not cover that page.

## Step 6 — Verify and record

- `vendor/bin/pint --dirty --format agent`
- `php artisan view:clear` before running tests — per `.ai/rules/app.md`, Pest
  reuses compiled Blade from `storage/framework/views` and a stale template
  silently passes or fails the next run.
- `php artisan test --compact` on the touched files, then ask the user to run
  the full suite.
- Check it in the browser, not only in rendered HTML: `.ai/rules/app.md` notes
  the app shell is hardcoded `<html class="dark">`, so the dark appearance is
  what actually ships. Confirm white initials read well on every palette entry
  against the dark shell.
- Record a rule with `record-rule` covering the "gradient lives in two places
  and must not drift" constraint, globbed to `app/Models/User.php` and
  `app/Filament/Resources/Users/UserResource.php`.

---

## Out of scope
- Changing the processed-image conversions or `config/images.php`.
- The upload/processing pipeline (`ProcessUploadedImage`) — untouched.
- Any new dependency.
