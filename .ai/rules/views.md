---
paths:
  - 'resources/views/**'
---

# Views

## Views read the business name from $businessName, never config('app.name')
The brand shown to users is the BusinessName setting, not APP_NAME. AppServiceProvider::configureViewComposers() shares $businessName with every view via a View::composer('*') — composed rather than View::share() so the settings table is not queried while booting requests that render no view.

Blade must use $businessName. Reintroducing config('app.name') in a view silently pins that spot to APP_NAME while the rest of the site follows the admin setting. Copy that embeds the name uses a :business placeholder (see welcome.blade.php) so it stays translatable.

SettingKey::BusinessName defaults to config('app.name') and casts through toFilledString(), so a blank or non-string row falls back instead of rendering an empty brand.
