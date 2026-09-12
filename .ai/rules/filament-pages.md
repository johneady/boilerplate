---
paths:
  - 'app/Settings/SettingKey.php, app/Filament/Pages/ManageSettings.php, config/images.php'
---

# Filament Pages

## The logo setting is one upload serving the mark, favicon and social image
SettingKey::Logo (key "logo") is not just a favicon source: its "logo" conversion set writes mark/favicon/apple-touch/social, and the "mark" one is the brand mark the site chrome renders.

Adding a conversion to an existing set does NOT backfill sets already on disk. Settings::logoUrl() resolves each conversion independently and returns null when the file is missing, so a set written before a conversion existed simply falls back (chrome to the bundled SVG, head to the bundled favicons) instead of emitting a broken link.

The settings table is key/value, so renaming a setting is a SettingKey change and never a migration -- the key name appears nowhere in the schema.

The SEO & brand tab leads with a preview (View::make('filament.settings.logo-preview')) that renders x-app-logo-icon itself rather than the stored URL, so it shows the bundled default too -- the state the buttons alone could not convey.

SettingsSeeder deliberately seeds NO logo row. It used to generate a flat indigo square; now that the bundled mark is designed artwork, a placeholder would only be a downgrade, and an absent row is what makes the component fall through to it.
