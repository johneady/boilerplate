---
paths:
  - 'app/Settings/**'
---

# Settings

## Add settings as SettingKey cases, never as columns
The `settings` table is key/value (unique `key`, nullable JSON `value`), so a new setting is a new App\Settings\SettingKey case, not a migration.

Each case declares its own default(), cast(), label() and helperText(). That is deliberate: a key with no row still reads back as a correctly typed value, so callers never handle "not saved yet". Read and write through App\Settings\Settings (a singleton, so the table is queried at most once per request) rather than the Setting model.

Settings::setMany() drops keys that are not SettingKey cases, so a stray form field cannot write a row nothing reads.

ManageSettings builds its form by mapping over SettingKey::cases(), so a new case needs a field added to its formComponent() match — a test asserts the form has a field for every case, which fails loudly if you forget.
