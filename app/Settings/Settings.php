<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     * Logo URLs already resolved for this instance, keyed by path|conversion.
     *
     * @var array<string, string|null>
     */
    private array $resolvedLogoUrls = [];

    /**
     * Read a setting, falling back to the key's declared default.
     */
    public function get(SettingKey $key): mixed
    {
        $stored = $this->all();

        if (! array_key_exists($key->value, $stored)) {
            return $key->default();
        }

        return $key->cast($stored[$key->value]);
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
     * Null until App\Jobs\ProcessUploadedImage has written the conversions,
     * and when no logo has been uploaded at all -- which is what lets the page
     * head fall back to the bundled favicon files, and the brand mark to the
     * bundled x-app-logo-icon SVG, rather than link a file that does not
     * exist. Like User::avatarUrl(), resolution is memoised because the head
     * alone renders it three times per page.
     */
    public function logoUrl(string $conversion): ?string
    {
        $directory = $this->string(SettingKey::Logo);

        if ($directory === '') {
            return null;
        }

        $cacheKey = $directory.'|'.$conversion;

        if (array_key_exists($cacheKey, $this->resolvedLogoUrls)) {
            return $this->resolvedLogoUrls[$cacheKey];
        }

        /** @var string $disk */
        $disk = config('images.disk');

        /** @var string $format */
        $format = config('images.format');

        $path = $directory.'/'.$conversion.'.'.$format;

        $storage = Storage::disk($disk);

        // A conversion can be missing if the set was written by an older
        // configuration; the bundled default beats a broken image link.
        return $this->resolvedLogoUrls[$cacheKey] = $storage->exists($path)
            ? $storage->url($path)
            : null;
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
        DB::transaction(function () use ($values): void {
            foreach ($values as $rawKey => $value) {
                $key = SettingKey::tryFrom((string) $rawKey);

                if ($key === null) {
                    continue;
                }

                Setting::updateOrCreate(
                    ['key' => $key->value],
                    ['value' => $key->cast($value)],
                );
            }
        });

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
            $values[$key->value] = $this->get($key);
        }

        return $values;
    }

    /**
     * Discard the in-memory cache, forcing the next read to hit the database.
     */
    private function flush(): void
    {
        $this->cache = null;
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
