---
paths:
  - 'resources/views/components/app-logo-icon.blade.php, resources/views/components/app-logo.blade.php'
---

# Components Views Components

## The brand mark is the uploaded logo, falling back to the bundled SVG
x-app-logo-icon renders the Logo setting's "mark" conversion when one is stored, and the bundled gradient SVG otherwise. $logoMarkUrl is composed onto EVERY view (AppServiceProvider's View::composer('*')), so no call site passes it -- that is what lets the sidebar, auth pages and public header pick up an upload without any layout knowing the setting exists.

The mark carries its own colour now, so do NOT reintroduce fill-current/text-* at call sites or an accent tile behind it in x-app-logo: both fight the gradient, and neither branch needs them.

The bolt is drawn in white rather than knocked out through evenodd. A knockout shows whatever sits behind the mark, which turned the bolt black on the dark auth backdrop -- that is the bug the explicit white path exists to prevent.

The gradient id is uniqued per render (Str::random). The mark appears more than once per page, and a duplicate id makes every later instance resolve the first one's stops.
