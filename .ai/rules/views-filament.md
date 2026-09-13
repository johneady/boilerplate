---
paths:
  - 'resources/views/filament/**'
---

# Views Filament

## x-filament::callout renders `description`, not its default slot
The Filament callout component only renders the `heading`, `description` and `footer` props. Content placed in the default slot is silently dropped -- the callout still renders, just without the body text, so it looks styled and correct in the browser.

Pass body text as `:description="..."`, never as slot content. A Livewire assertSee() on the detail text is what catches this; the page itself gives no error.

Hit while building the Diagnostics tab's findings (resources/views/filament/settings/diagnostics-report.blade.php).
