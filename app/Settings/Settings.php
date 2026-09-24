<?php

namespace App\Settings;

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Media\MediaCollection;
use App\Models\Media;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Typed read/write access to the key/value settings store.
 *
 * Bound as a scoped instance, so the whole table is loaded at most once per
 * request no matter how many settings are read -- settings are consulted from
 * places like middleware and views where a query per lookup would add up --
 * while a queue worker still re-reads between jobs rather than holding the
 * values it booted with. See AppServiceProvider::register().
 */
class Settings
{
    /**
     * Every stored setting, keyed by its raw key, or null before the first read.
     *
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * Logo URLs already resolved for this instance, keyed by conversion.
     *
     * @var array<string, string|null>
     */
    private array $resolvedLogoUrls = [];

    /**
     * The resolved logo row, or false when it has not been looked up yet.
     *
     * False rather than null as the "not yet resolved" marker, because null is
     * the legitimate answer for an installation with no logo -- and that answer
     * must be cached too, or every render re-queries to learn it again.
     */
    private Media|false|null $resolvedLogoMedia = false;

    /**
     * Encrypted settings whose stored value would not decrypt, keyed by value.
     *
     * Recorded rather than rethrown so a rotated APP_KEY degrades to "not
     * configured" -- and is reported once and surfaced by the payment
     * diagnostics -- instead of taking every page that reads a setting down.
     *
     * @var array<string, true>
     */
    private array $undecryptable = [];

    /**
     * Read a setting, falling back to the key's declared default.
     */
    public function get(SettingKey $key): mixed
    {
        $stored = $this->all();

        if (! array_key_exists($key->value, $stored)) {
            return $key->default();
        }

        $value = $stored[$key->value];

        if ($key->isEncrypted()) {
            $value = $this->decrypt($key, $value);
        }

        return $key->cast($value);
    }

    /**
     * An encrypted setting as it may be shown: "sk_live_…a1b2", or '' if unset.
     *
     * Only the last four characters are revealed, plus a known non-secret
     * prefix (Stripe's key type), which is enough to tell a sandbox key from
     * a live one and to confirm which key is stored.
     */
    public function masked(SettingKey $key): string
    {
        $value = $this->string($key);

        if ($value === '') {
            return '';
        }

        $prefix = preg_match('/^(sk_test_|sk_live_|rk_test_|rk_live_|whsec_)/', $value, $matches) === 1 ? $matches[1] : '';

        return $prefix.'…'.substr($value, -4);
    }

    /**
     * The encrypted settings that are stored but cannot be decrypted.
     *
     * Non-empty after APP_KEY is rotated without the old key listed in
     * APP_PREVIOUS_KEYS: the credentials are still in the table, but nothing
     * can read them.
     *
     * @return list<SettingKey>
     */
    public function undecryptableKeys(): array
    {
        $keys = array_filter(SettingKey::cases(), fn (SettingKey $key): bool => $key->isEncrypted());

        foreach ($keys as $key) {
            $this->get($key);
        }

        return array_values(array_filter($keys, fn (SettingKey $key): bool => isset($this->undecryptable[$key->value])));
    }

    /**
     * Read a setting known to hold a boolean.
     */
    public function boolean(SettingKey $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * Read a setting known to hold a string.
     */
    public function string(SettingKey $key): string
    {
        return (string) $this->get($key);
    }

    /**
     * Whether a row has ever been stored for this key.
     *
     * Distinguishes "never saved" from "saved with the declared default",
     * which get() alone cannot: reading an unsaved key returns the default
     * either way. That is what lets the mail configuration stand down to the
     * environment until an administrator has actually chosen a mailer.
     */
    public function has(SettingKey $key): bool
    {
        return array_key_exists($key->value, $this->all());
    }

    /**
     * The name the application trades under, shown wherever it is branded.
     */
    public function businessName(): string
    {
        return $this->string(SettingKey::BusinessName);
    }

    /**
     * Get the URL of one of the uploaded logo's conversions.
     *
     * Reads the ownerless Logo media collection. The logo belongs to the
     * installation rather than to any record, so its rows carry no owner --
     * which is also why this cannot go through App\Concerns\HasMedia, whose
     * every method starts from a model.
     *
     * Null until App\Jobs\ProcessUploadedImage has written the conversions,
     * and when no logo has been uploaded at all -- which is what lets the page
     * head fall back to the bundled favicon files, and the brand mark to the
     * bundled x-app-logo-icon SVG, rather than link a file that does not
     * exist. Resolution is memoised because the head alone renders it three
     * times per page.
     */
    public function logoUrl(string $conversion): ?string
    {
        if (array_key_exists($conversion, $this->resolvedLogoUrls)) {
            return $this->resolvedLogoUrls[$conversion];
        }

        return $this->resolvedLogoUrls[$conversion] = $this->logoMedia()?->url($conversion);
    }

    /**
     * The current logo row, if one has been uploaded.
     *
     * Memoised separately from the URLs: the head asks for three conversions
     * of the same logo, and without this that is three queries for one row.
     */
    public function logoMedia(): ?Media
    {
        if ($this->resolvedLogoMedia !== false) {
            return $this->resolvedLogoMedia;
        }

        try {
            return $this->resolvedLogoMedia = Media::query()
                ->inCollection(MediaCollection::Logo)
                ->whereNull('model_id')
                ->whereNull('model_type')
                ->latest('id')
                ->first();
        } catch (QueryException $e) {
            if (! $this->isConnectionFailure($e)) {
                throw $e;
            }

            // The same contract the settings table itself has: an unreachable
            // database must not stop an error page rendering. The head asks for
            // the logo on EVERY page, the 500 page included, so throwing here
            // would mean a database outage produced no error page at all --
            // only a second, uglier failure. Falling back to the bundled mark
            // is the whole point of logoUrl() returning null.
            report($e);

            return $this->resolvedLogoMedia = null;
        }
    }

    /**
     * Discard the memoised logo, so the next read goes back to the database.
     *
     * Required because the upload and remove actions persist out-of-band and
     * then RE-RENDER in the same request: without this the page redraws from
     * the row memoised before the change, and an administrator is shown the
     * logo they just deleted. Settings is bound scoped, so the instance --
     * and its memo -- outlives the action that changed the underlying row.
     */
    public function forgetLogo(): void
    {
        $this->resolvedLogoMedia = false;
        $this->resolvedLogoUrls = [];
    }

    /**
     * The schema.org Organization description of this site, as an array.
     *
     * Returned as data rather than rendered JSON so the head partial can hand
     * it straight to json_encode(): building the JSON in Blade risks an
     * unescaped quote in a business name breaking the whole script block --
     * and a malformed one is worse than none, because a search engine may
     * discard every other signal on the page with it.
     *
     * Optional details are omitted rather than emitted empty; a blank
     * telephone is a worse claim than no telephone at all.
     *
     * @return array<string, mixed>
     */
    public function organizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->businessName(),
            'url' => url('/'),
        ];

        if (($logo = $this->logoUrl('social')) !== null) {
            $schema['logo'] = url($logo);
        }

        if (($description = $this->string(SettingKey::SeoDescription)) !== '') {
            $schema['description'] = $description;
        }

        if (($email = $this->string(SettingKey::BusinessEmail)) !== '') {
            $schema['email'] = $email;
        }

        if (($phone = $this->string(SettingKey::BusinessPhone)) !== '') {
            $schema['telephone'] = $phone;
        }

        if (($address = $this->string(SettingKey::BusinessAddress)) !== '') {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ];
        }

        return $schema;
    }

    /**
     * The mailer messages actually send through, not merely the stored choice.
     *
     * Mirrors the conditions AppServiceProvider::configureMailFromSettings()
     * applies: the environment's own mailer stays in charge until a mailer
     * has been saved, a saved non-SMTP choice replaces it with log, and an
     * SMTP row without a host fails closed to log. Keep the two in step when
     * either changes -- the settings page's mailer button reports this value.
     */
    public function effectiveMailer(): string
    {
        if (! $this->has(SettingKey::MailMailer)) {
            return (string) config('mail.default');
        }

        if ($this->string(SettingKey::MailMailer) !== 'smtp' || $this->string(SettingKey::MailHost) === '') {
            return 'log';
        }

        return 'smtp';
    }

    /**
     * Format a date as the administrator configured, in the display timezone.
     */
    public function formatDate(?CarbonInterface $date): string
    {
        return $this->format($date, $this->string(SettingKey::DateFormat));
    }

    /**
     * Format a time of day as the administrator configured, in the display
     * timezone.
     */
    public function formatTime(?CarbonInterface $date): string
    {
        return $this->format($date, $this->string(SettingKey::TimeFormat));
    }

    /**
     * Format a full date and time as the administrator configured, in the
     * display timezone.
     *
     * The date and time formats are chosen separately on the settings page;
     * this is where they meet, so a full timestamp is never formatted by a
     * call site inventing its own combination.
     */
    public function formatDateTime(?CarbonInterface $date): string
    {
        return $this->format($date, $this->string(SettingKey::DateFormat).', '.$this->string(SettingKey::TimeFormat));
    }

    /**
     * Render a date as a relative time such as "2 hours ago", localised.
     *
     * Timezone-independent -- "2 hours ago" is the same distance in every
     * timezone -- but the words follow the locale setting, like the formats.
     */
    public function formatRelative(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        return $date->locale($this->string(SettingKey::Locale))->diffForHumans();
    }

    /**
     * Convert a date to the display timezone and format it, with month and
     * day names translated per the locale setting.
     *
     * Storage is pinned to UTC in config, so converting HERE and only here is
     * what keeps the display timezone a presentation choice: a model's
     * attribute stays UTC, and changing the setting never reinterprets a
     * stored timestamp. Null reads as the empty string so call sites can hand
     * nullable attributes straight over.
     */
    private function format(?CarbonInterface $date, string $format): string
    {
        if ($date === null) {
            return '';
        }

        return $date
            ->locale($this->string(SettingKey::Locale))
            ->timezone($this->string(SettingKey::Timezone))
            ->translatedFormat($format);
    }

    /**
     * Write a single setting.
     */
    public function set(SettingKey $key, mixed $value): void
    {
        $this->setMany([$key->value => $value]);
    }

    /**
     * Write several settings at once.
     *
     * Keys absent from SettingKey are ignored rather than stored, so a stray
     * field in a submitted form cannot write a row nothing will ever read.
     *
     * @param  array<string, mixed>  $values  Keyed by SettingKey value.
     */
    public function setMany(array $values): void
    {
        $changes = [];

        DB::transaction(function () use ($values, &$changes): void {
            foreach ($values as $rawKey => $value) {
                $key = SettingKey::tryFrom((string) $rawKey);

                if ($key === null) {
                    continue;
                }

                $cast = $key->cast($value);

                // Read before writing so the trail can say what a setting
                // changed FROM. Cheap: the whole table is already cached on
                // this instance, so this is an array lookup, not a query.
                $previous = $this->get($key);

                Setting::updateOrCreate(
                    ['key' => $key->value],
                    ['value' => $this->encryptForStorage($key, $cast)],
                );

                if ($previous !== $cast) {
                    $changes[$key->value] = $this->auditableSettingChange($key, $previous, $cast);
                }
            }
        });

        if ($changes !== []) {
            app(AuditLogger::class)->record(AuditEvent::SettingsUpdated, ['settings' => $changes]);
        }

        $this->flush();
    }

    /**
     * Every setting as it would be read, defaults included.
     *
     * This is what fills the admin panel's form, so a key that has never been
     * saved still arrives with its declared default rather than as null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [];

        foreach (SettingKey::cases() as $key) {
            // Encrypted settings are read one at a time, by name, never in
            // bulk: this array fills the admin form, so anything in it ends
            // up in the Livewire payload in the browser.
            if ($key->isEncrypted()) {
                continue;
            }

            $values[$key->value] = $this->get($key);
        }

        return $values;
    }

    /**
     * The value to write for a setting: ciphertext for an encrypted one.
     *
     * A blank stays blank, so "not configured" remains distinguishable from a
     * configured value without decrypting anything.
     */
    private function encryptForStorage(SettingKey $key, mixed $value): mixed
    {
        if (! $key->isEncrypted() || ! is_string($value) || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a stored value, degrading to blank when it cannot be.
     */
    private function decrypt(SettingKey $key, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            if (! isset($this->undecryptable[$key->value])) {
                $this->undecryptable[$key->value] = true;
                report($e);
            }

            return '';
        }
    }

    /**
     * Describe one setting change for the audit trail.
     *
     * Secret-bearing settings record THAT they changed and nothing more. The
     * mail password is the obvious one, and it is exactly the kind of value
     * that ends up copied into a table every administrator can read and that
     * outlives the change by a year of retention. The key name alone answers
     * the question the trail is for -- who changed the mail credentials and
     * when -- without becoming a place to read them.
     *
     * @return array{from: mixed, to: mixed}|array{redacted: true}
     */
    private function auditableSettingChange(SettingKey $key, mixed $previous, mixed $current): array
    {
        if ($key->isSecret()) {
            return ['redacted' => true];
        }

        return ['from' => $previous, 'to' => $current];
    }

    /**
     * Discard the in-memory cache, forcing the next read to hit the database.
     */
    private function flush(): void
    {
        $this->cache = null;
        $this->undecryptable = [];
    }

    /**
     * The raw stored rows, loaded once per request.
     *
     * An unreachable database reads as "nothing stored" rather than throwing,
     * so every caller falls back to the keys' declared defaults. This is what
     * lets the error pages render during the outage that caused them: the
     * global View::composer('*') resolves the business name for EVERY view,
     * so without this a 500 caused by the database throws again inside the
     * 500 page and the user gets Laravel's unstyled fallback -- the exact
     * moment resources/views/errors exists for.
     *
     * Deliberately narrow: only a connection-level failure is swallowed, and
     * the empty result is NOT cached, so a transient outage does not pin the
     * process to defaults once the database returns. A malformed row or a
     * missing column still surfaces, because those are bugs to fix rather
     * than conditions to degrade through.
     *
     * @return array<string, mixed>
     */
    private function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            return $this->cache = Setting::query()
                ->pluck('value', 'key')
                ->all();
        } catch (QueryException $e) {
            if (! $this->isConnectionFailure($e)) {
                throw $e;
            }

            report($e);

            return [];
        }
    }

    /**
     * Whether the query failed because the database could not be reached.
     *
     * Matched on the framework's own typed exceptions first: Laravel wraps a
     * missing SQLite file and a dropped connection in dedicated classes, and
     * neither carries a usable SQLSTATE (the SQLite case arrives with code 0
     * and no errorInfo at all), so sniffing vendor error numbers alone misses
     * exactly the cases that matter.
     *
     * SQLSTATE 08xxx -- the standard connection-exception class -- and the
     * MySQL/MariaDB connection codes cover a server that is up but
     * unreachable or refusing the credentials.
     *
     * "No such table" is deliberately NOT included: a missing settings table
     * means migrations have not run, which must stay loud rather than quietly
     * serving defaults.
     */
    private function isConnectionFailure(QueryException $e): bool
    {
        $previous = $e->getPrevious();

        // Checked on the PREVIOUS exception only: both of these are siblings
        // of QueryException rather than subclasses, so they arrive wrapped.
        if ($previous instanceof LostConnectionException
            || $previous instanceof SQLiteDatabaseDoesNotExistException) {
            return true;
        }

        if (str_starts_with((string) $e->getCode(), '08')) {
            return true;
        }

        return in_array((int) ($e->errorInfo[1] ?? 0), [
            1045, // MySQL/MariaDB: access denied
            2002, // MySQL/MariaDB: connection refused
            2003, // MySQL/MariaDB: cannot connect to server
            2006, // MySQL/MariaDB: server has gone away
        ], true);
    }
}
