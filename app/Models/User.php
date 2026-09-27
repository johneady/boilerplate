<?php

namespace App\Models;

use App\Auth\AccountRemovalRefused;
use App\Auth\Permission;
use App\Auth\Role;
use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\HasRoles;
use App\Media\HoldsMedia;
use App\Media\MediaCollection;
use App\Payments\Actions\EndSubscriptionsForDeletedUser;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use RuntimeException;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $deactivated_at
 * @property Role $role
 * @property bool $is_admin Derived from $role; see App\Concerns\HasRoles.
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HoldsMedia, MustVerifyEmail, PasskeyUser
{
    /**
     * Hue pairs for the initials-avatar gradient, light stop first.
     *
     * APPEND-ONLY, like Flux's own avatar colour list: an entry is selected by
     * crc32 modulo the array length, so inserting or reordering entries would
     * change the colour of every user whose hash lands after the change. New
     * pairs go at the end, never in between.
     *
     * The stops are deliberately deep rather than pale: initials render white
     * on top in both themes, so every pair must keep white text legible.
     *
     * @var list<array{from: string, to: string}>
     */
    private const array AVATAR_GRADIENTS = [
        ['from' => '#dc2626', 'to' => '#991b1b'],
        ['from' => '#c2410c', 'to' => '#7c2d12'],
        ['from' => '#b45309', 'to' => '#78350f'],
        ['from' => '#047857', 'to' => '#064e3b'],
        ['from' => '#0f766e', 'to' => '#134e4a'],
        ['from' => '#0284c7', 'to' => '#075985'],
        ['from' => '#4f46e5', 'to' => '#3730a3'],
        ['from' => '#7c3aed', 'to' => '#5b21b6'],
        ['from' => '#db2777', 'to' => '#9d174d'],
        ['from' => '#e11d48', 'to' => '#9f1239'],
    ];

    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasMedia, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Avatar URLs already resolved for this instance, keyed by conversion.
     *
     * @var array<string, string|null>
     */
    protected array $resolvedAvatarUrls = [];

    /**
     * The model's default attribute values.
     *
     * The users.role column has the same default, which covers an INSERT; this
     * covers the instance BEFORE it is saved. Without it a `new User` has a
     * null role, and every role check on it -- including the one
     * canAccessPanel() makes -- fails on a null dereference rather than
     * reading as the least-privileged role.
     *
     * deactivated_at is here for the same reason: a freshly created instance
     * holds only what was inserted, and isDeactivated() -- asked on every
     * request by EnsureAccountIsActive -- would otherwise hit the strict
     * missing-attribute check until the model was reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::DEFAULT->value,
        'deactivated_at' => null,
    ];

    protected static function booted(): void
    {
        // Every deletion path -- the settings page, the admin panel, tinker --
        // passes through here. The integrity checks come first so a refused
        // deletion never cancels a subscription on its way to failing; then
        // the gateway stops billing the account, and a gateway that refuses
        // stops the deletion. See EndSubscriptionsForDeletedUser.
        static::deleting(function (User $user): void {
            if ($user->isLastActiveAdministrator()) {
                throw AccountRemovalRefused::lastAdministrator($user);
            }

            // Also enforced by the restrictOnDelete foreign keys; refusing
            // here turns a constraint violation into an error that says why.
            if ($user->hasFinancialRecords()) {
                throw AccountRemovalRefused::holdsFinancialRecords($user);
            }

            app(EndSubscriptionsForDeletedUser::class)->handle($user);
        });

        // Demoting the last active administrator locks everyone out as surely
        // as deleting them. The panel already stops an administrator demoting
        // themselves; this covers seeders, commands and tinker. Checked on
        // the ORIGINAL role and deactivation, since those are what the
        // account held until this save.
        static::updating(function (User $user): void {
            if (! $user->isDirty('role') || $user->role === Role::Admin || $user->getOriginal('role') !== Role::Admin) {
                return;
            }

            if ($user->getOriginal('deactivated_at') !== null) {
                return;
            }

            // Takes effect only when the save runs inside a transaction, as
            // UserResource::saveUser()'s does; see lockActiveAdministrators().
            $user->lockActiveAdministrators();

            if (! static::query()->withRole(Role::Admin)->active()->whereKeyNot($user->getKey())->exists()) {
                throw AccountRemovalRefused::lastAdministrator($user);
            }
        });
    }

    /**
     * Delete the account.
     *
     * Overridden only to declare what the deleting event above can throw:
     * callers such as the admin panel catch both to keep the account and say
     * why, and without this nothing on the call path shows they can happen.
     *
     * @throws AccountRemovalRefused when the account is the last active administrator, or is named on financial records
     * @throws GatewayException when a gateway cannot confirm a live subscription was cancelled
     */
    public function delete(): ?bool
    {
        // Only removing an administrator can race into a lockout: two
        // administrators deleting each other at the same moment would each
        // pass the last-administrator check in the deleting event. Holding
        // the active administrators' rows serializes the two, so the second
        // re-reads after the first commits and is refused.
        if ($this->role !== Role::Admin) {
            return parent::delete();
        }

        return DB::transaction(function (): ?bool {
            $this->lockActiveAdministrators();

            return parent::delete();
        });
    }

    /**
     * Attributes kept out of the audit trail beyond the global denylist.
     *
     * The secrets themselves (password, two_factor_secret, the recovery codes,
     * remember_token) are excluded globally in config('audit.never_record'),
     * not here -- they must stay out whatever writes them. These two are
     * merely noise: Fortify rewrites two_factor_confirmed_at as part of
     * enrolment, and updated_at changes on every save, so an entry would list
     * them alongside whatever actually changed.
     *
     * The `*_exists` flags are not columns at all: the admin table loads them
     * with withExists() (see hasFinancialRecords()), and a user deleted from
     * there would otherwise carry them into the entry's captured attributes.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [
            'two_factor_confirmed_at',
            'updated_at',
            'recorded_payments_exists',
            'initiated_refunds_exists',
            'running_subscription_exists',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Immutable, as the @property docblocks above promise and the
            // payment models already do -- nothing here is ever mutated in
            // place. Fortify's markEmailAsVerified() assigns now(), which the
            // cast accepts and converts.
            'email_verified_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'role' => Role::class,
            'password' => 'hashed',
        ];
    }

    /**
     * The email address, always stored lowercase.
     *
     * Fortify lowercases the address typed at sign-in (lowercase_usernames),
     * so a stored "Jane@Example.com" could never sign in on PostgreSQL or
     * SQLite, which compare case-sensitively -- only MySQL's case-insensitive
     * collation hid it. Lowercasing here covers every path that writes the
     * address, the profile page and the admin panel included.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower($value));
    }

    /**
     * Determine whether the user may access a Filament panel.
     *
     * The panel has no login page of its own, so this is purely an
     * authorization check on an already-authenticated Fortify session.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // The panel runs its own middleware stack rather than the web group,
        // so EnsureAccountIsActive never sees its requests: this is the
        // panel's half of that check.
        return ! $this->isDeactivated() && $this->hasPermission(Permission::AccessAdminPanel);
    }

    /**
     * Whether an administrator has deactivated this account.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Scope a query to accounts that have not been deactivated.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Whether this is the only active administrator left.
     *
     * Deleting or deactivating that account leaves nobody able to reach the
     * users table or the settings, with no way back short of the database.
     */
    public function isLastActiveAdministrator(): bool
    {
        return $this->role === Role::Admin
            && ! $this->isDeactivated()
            && ! static::query()->withRole(Role::Admin)->active()->whereKeyNot($this->getKey())->exists();
    }

    /**
     * Whether payments or refunds name this account as who recorded or issued them.
     *
     * Those columns are write-once on the financial records, so the account
     * cannot be deleted while they point at it -- only deactivated. Reads the
     * `*_exists` attributes when the admin table has loaded them with
     * withExists(), so rendering a page of users costs no query per row.
     */
    public function hasFinancialRecords(): bool
    {
        if (array_key_exists('recorded_payments_exists', $this->attributes)
            && array_key_exists('initiated_refunds_exists', $this->attributes)) {
            return (bool) $this->attributes['recorded_payments_exists']
                || (bool) $this->attributes['initiated_refunds_exists'];
        }

        return $this->recordedPayments()->exists() || $this->initiatedRefunds()->exists();
    }

    /**
     * Whether a subscription is trialing, active or past due -- billing, or
     * about to -- for this account.
     *
     * An unfinished checkout is not counted: deactivate() expires those at the
     * gateway itself. Like hasFinancialRecords(), reads a loaded
     * `running_subscription_exists` before falling back to a query.
     */
    public function hasRunningSubscription(): bool
    {
        if (array_key_exists('running_subscription_exists', $this->attributes)) {
            return (bool) $this->attributes['running_subscription_exists'];
        }

        return $this->runningSubscription()->exists();
    }

    /**
     * Deactivate the account: it can no longer sign in, and is signed out now.
     *
     * The offboarding path for staff. Unlike delete(), the row stays, so every
     * post, payment, refund and audit entry naming this person keeps naming
     * them. The remember token is rotated and database sessions are purged so
     * access ends immediately rather than on the next sign-in;
     * EnsureAccountIsActive and canAccessPanel() catch anything that slips by.
     *
     * A running subscription must be cancelled first: the customer would go
     * on being billed with no way to sign in and stop it. An unfinished
     * checkout is expired at the gateway here instead, as deleting the
     * account does, so it cannot be completed after the account is locked.
     *
     * @throws AccountRemovalRefused
     * @throws GatewayException when a gateway cannot confirm an unfinished checkout was expired
     */
    public function deactivate(): void
    {
        // Before the transaction, so the gateway call holds no locks. A
        // checkout finished at the last moment comes back started, and the
        // running-subscription check below then refuses.
        $reconcile = app(ReconcileSubscription::class);

        Subscription::query()
            ->where('active_user_id', $this->getKey())
            ->where('status', SubscriptionStatus::Incomplete->value)
            ->get()
            ->each(fn (Subscription $checkout): Subscription => $reconcile->expire($checkout));

        // In a transaction holding the active administrators' rows, for the
        // race delete() describes: two administrators deactivating each other.
        DB::transaction(function (): void {
            if ($this->role === Role::Admin) {
                $this->lockActiveAdministrators();
            }

            if ($this->isLastActiveAdministrator()) {
                throw AccountRemovalRefused::lastAdministrator($this);
            }

            // Queried, not read from a flag the admin table loaded: the
            // expiry above may have found the checkout completed.
            if ($this->runningSubscription()->exists()) {
                throw AccountRemovalRefused::hasRunningSubscription($this);
            }

            $this->forceFill([
                'deactivated_at' => now(),
                'remember_token' => Str::ulid(),
            ])->save();
        });

        $this->endSessions();
    }

    /**
     * End this user's database-backed sessions, optionally keeping one.
     *
     * Shared by the password change (which keeps the session making the
     * change), the password reset and deactivation. A session driver other
     * than `database` needs its own handler here, not deletion of the guard --
     * see .ai/rules/auth-gates.md.
     */
    public function endSessions(?string $except = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $this->getKey())
            ->when($except !== null, fn ($query) => $query->whereNot('id', $except))
            ->delete();
    }

    /**
     * Lock the active administrators' rows for the rest of the transaction.
     *
     * A locking read waits for any other transaction holding them, then reads
     * what that one committed -- so the last-administrator check that follows
     * sees a concurrent removal instead of racing it. SQLite has no row locks
     * and ignores this, but serializes writing transactions anyway.
     */
    private function lockActiveAdministrators(): void
    {
        static::query()->withRole(Role::Admin)->active()->lockForUpdate()->pluck($this->getKeyName());
    }

    /**
     * Let a deactivated account sign in again with its existing credentials.
     */
    public function reactivate(): void
    {
        $this->forceFill(['deactivated_at' => null])->save();
    }

    /**
     * Whether the account holder may close the account from their own settings.
     *
     * Staff accounts are offboarded by an administrator, who deactivates them
     * so their name stays on what they worked on -- and an administrator
     * closing their own account could leave the panel with nobody to run it.
     * An account named on financial records (a former member of staff) cannot
     * be deleted by anyone, so it is not offered either.
     */
    public function canCloseOwnAccount(): bool
    {
        return ! $this->hasPermission(Permission::AccessAdminPanel)
            && ! $this->hasFinancialRecords();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Deterministic gradient for this user's initials avatar.
     *
     * Seeded from the immutable primary key rather than name or email so a
     * user who corrects a typo in their name keeps the avatar colour their
     * colleagues recognise. Returns two hex stops, light-to-dark, for the SVG
     * and CSS paths alike -- Filament renders an <img>, the Flux sites an
     * inline style, and a user must not be teal in one and orange in the
     * other.
     *
     * @return array{from: string, to: string}
     */
    public function avatarGradient(): array
    {
        // Unsaved models (factories in tests) have no key yet, so the name
        // stands in until the first save assigns one.
        $seed = (string) ($this->getKey() ?? $this->name);

        return self::AVATAR_GRADIENTS[crc32($seed) % count(self::AVATAR_GRADIENTS)];
    }

    /**
     * Inline background for the initials fallback on the Flux avatar components.
     *
     * Flux builds its avatar background from fixed Tailwind classes with no
     * gradient path, so this is applied as a style attribute. Its flat zinc
     * default is wrapped in [:where(&)] -- zero specificity -- so the inline
     * style wins without !important.
     */
    public function avatarGradientStyle(): string
    {
        $gradient = $this->avatarGradient();

        return sprintf('background-image:linear-gradient(135deg,%s,%s)', $gradient['from'], $gradient['to']);
    }

    /**
     * A data-URI SVG of the initials on this user's gradient, for render
     * paths that need an image rather than an inline style: the Filament
     * users table and the panel's avatar provider.
     *
     * Generated inline rather than fetched from a third-party initials
     * service: Filament's own fallback asks ui-avatars.com, which would leak
     * the signed-in user's name to that service on every panel page view.
     *
     * This is the single markup builder for every data-URI render path, the
     * same way avatarGradientStyle() is for the inline-style ones, so the
     * paths cannot drift apart.
     */
    public function initialsAvatarUrl(): string
    {
        // Initials derive from the user-supplied name, so they reach this SVG
        // as untrusted text: a name beginning with "<" yields initials that
        // would otherwise break out of the <text> element. Escaped rather
        // than stripped so legitimate names such as "A&B" still render.
        $initials = e(Str::of($this->initials())->trim()->upper()->value());

        $gradient = $this->avatarGradient();

        // Unique per user: several of these render on one table page, and a
        // collision would matter again if the SVG is ever inlined.
        $gradientId = 'grad-'.$this->getKey();

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                <defs>
                    <linearGradient id="{$gradientId}" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="{$gradient['from']}"/>
                        <stop offset="1" stop-color="{$gradient['to']}"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" fill="url(#{$gradientId})"/>
                <text x="50%" y="50%" fill="#ffffff"
                      font-family="system-ui, sans-serif" font-size="26" font-weight="500"
                      text-anchor="middle" dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Payments attributed to this user's account, whatever they were for.
     *
     * The payable a payment actually settles is the morphTo on Payment
     * itself; this is only the "the customer's own payment history" path
     * (the billing settings page).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Blog posts credited to this user as their author.
     *
     * author_id is nullable and null on delete: the posts outlive the
     * account, and the public byline falls back to the business name.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /**
     * Manual payments this user recorded from the admin panel.
     *
     * @return HasMany<Payment, $this>
     */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    /**
     * Refunds this user issued from the admin panel.
     *
     * @return HasMany<Refund, $this>
     */
    public function initiatedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'initiated_by');
    }

    /**
     * The subscription trialing, active or past due for this user, if any.
     *
     * Read through active_user_id, which is set only while a subscription is
     * live and is unique, so there is at most one. A live subscription still
     * at checkout (Incomplete) is left out: nothing bills until it starts.
     *
     * @return HasOne<Subscription, $this>
     */
    public function runningSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'active_user_id')
            ->where('status', '!=', SubscriptionStatus::Incomplete->value);
    }

    /**
     * This user's customer records at the gateways, one per gateway and
     * mode, created on first subscribe so repeat checkouts and the billing
     * portal have one to attach to.
     *
     * @return HasMany<BillingCustomer, $this>
     */
    public function billingCustomers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    /**
     * The subscription this user's access and billing page are about: the
     * live one, or failing that the most recent one that started.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with(['plan', 'price', 'pendingPrice'])
            ->currentFirst()
            ->first();
    }

    /**
     * Whether a new subscription would come with the plan's free trial.
     *
     * One trial per customer: anyone who has started a subscription before in
     * this mode -- trialing, paying, lapsed or cancelled -- starts the next one
     * paying. A checkout abandoned before it started does not count.
     */
    public function isEligibleForTrial(GatewayMode $mode): bool
    {
        return ! $this->subscriptions()
            ->where('mode', $mode->value)
            ->whereIn('status', SubscriptionStatus::started())
            ->exists();
    }

    /**
     * Whether the user may use a subscription's benefits right now.
     *
     * Any plan when $planKey is null, or the plan with that key. See
     * Subscription::grantsAccess() for the trial, grace-period and
     * paid-until-period-end rules.
     *
     * A sandbox subscription counts only while the site is in sandbox mode:
     * it is paid for with a test card, and one left running after the switch
     * to live would otherwise be free access for as long as it renews.
     */
    public function subscribed(?string $planKey = null): bool
    {
        $settings = app(Settings::class);
        $graceDays = $settings->integer(SettingKey::PastDueGraceDays);
        $modes = $settings->string(SettingKey::PaymentsMode) === GatewayMode::Sandbox->value
            ? [GatewayMode::Sandbox->value, GatewayMode::Live->value]
            : [GatewayMode::Live->value];

        return $this->subscriptions()
            ->with('plan')
            ->whereIn('mode', $modes)
            ->whereIn('status', SubscriptionStatus::grantingAccess())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get()
            ->contains(fn (Subscription $subscription): bool => $subscription->grantsAccess($graceDays)
                && ($planKey === null || $subscription->plan?->key === $planKey));
    }

    /**
     * Get the two factor authentication QR code URL.
     *
     * Overridden to label the authenticator entry with the BusinessName
     * setting rather than config('app.name'), which is what Fortify's
     * TwoFactorAuthenticatable hardcodes and which would otherwise leave every
     * user's authenticator app showing APP_NAME while the rest of the
     * application follows the admin setting. Fortify exposes no hook for the
     * issuer, so the trait method is replaced outright; the body is otherwise
     * the trait's, and should be rechecked when Fortify is upgraded.
     */
    public function twoFactorQrCodeUrl(): string
    {
        $secret = $this->two_factor_secret;

        // Null for every user who has not started enrolment. The trait's
        // version passes it straight to decrypt() and dies on a TypeError;
        // throwing something catchable keeps the Security page's existing
        // "Failed to fetch setup data" handling working if it ever reaches
        // here without checking first, as Fortify's own twoFactorQrCodeSvg()
        // would.
        if ($secret === null) {
            throw new RuntimeException('Two factor authentication is not configured for this user.');
        }

        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            app(Settings::class)->businessName(),
            $this->{Fortify::username()},
            Fortify::currentEncrypter()->decrypt($secret)
        );
    }

    /**
     * Get the URL of one of the user's avatar conversions.
     *
     * A facade over the media collection rather than a column read. The avatar
     * became an App\Models\Media row so it could carry who uploaded it and be
     * deleted with its files, but every caller -- the sidebar, the user menu,
     * Filament's HasAvatar contract -- still asks this one question, so the
     * signature stayed put.
     *
     * Null until App\Jobs\ProcessUploadedImage has written the conversions,
     * which is what lets the UI fall back to initials during the short window
     * between upload and processing -- the unprocessed original is never
     * served, because it has not been stripped of metadata yet.
     */
    public function avatarUrl(string $conversion = 'thumb'): ?string
    {
        // The sidebar and the user menu each render an avatar, so this is
        // called several times per request. Memoised here because the lookup
        // that costs a QUERY is finding the media row; Media::url() memoises
        // the disk check on top of that for its own callers.
        if (array_key_exists($conversion, $this->resolvedAvatarUrls)) {
            return $this->resolvedAvatarUrls[$conversion];
        }

        return $this->resolvedAvatarUrls[$conversion] = $this->mediaUrl(
            MediaCollection::Avatar,
            $conversion,
        );
    }

    /**
     * Get the avatar URL shown in the Filament admin panel.
     *
     * Filament resolves this through its HasAvatar contract; returning null
     * lets the panel fall back to its own initials-based default.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }
}
