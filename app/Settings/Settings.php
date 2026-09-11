<?php

namespace App\Settings;

use App\Models\Setting;
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
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return $this->cache ??= Setting::query()
            ->pluck('value', 'key')
            ->all();
    }
}
