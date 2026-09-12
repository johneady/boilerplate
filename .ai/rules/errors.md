---
paths:
  - 'resources/views/errors/**, resources/views/components/errors/**'
---

# Errors

## Error pages must render with the database down
resources/views/errors/{403,404,419,429,500,503}.blade.php deliberately do NOT extend x-layouts::app or x-layouts::auth and do not include partials.head. A 500 is most often a database outage, and the View::composer('*') that supplies $businessName reads the settings table while partials.head reads six more settings plus the image disk -- inheriting either means the error view throws while rendering and the user gets Laravel's unstyled fallback at exactly the moment these templates exist for.

So x-errors.layout brands from config('app.name'). This is the one intentional exception to the .ai/rules/views.md rule that views read $businessName. x-app-logo-icon IS safe and is used: the composer resolves Settings lazily and logoUrl() short-circuits on the unset default before querying. Anything added to these pages that reads a setting, or the image disk with a logo stored, breaks that property -- tests/Feature/ErrorPagesTest.php asserts every page still renders against a nonexistent database.

Also: the `errors::` namespace is registered LAZILY by the exception handler (RegisterErrorViewPaths), so it does not exist on a normal request -- a test must call `(new RegisterErrorViewPaths)()` first. And view()->exists('errors::404') alone is a false pass, because the framework bundles its own views in the same namespace; assert getPath() resolves to resource_path('views/errors/...').

Links are plain hrefs ("/", "/login") not route(), and carry no wire:navigate: a failed route cache is one way a 500 happens, and route() would throw inside the error page.

## Error pages are light mode with inline SVG figures
The error pages are LIGHT MODE on purpose, unlike the signed-in shell which is hardcoded dark (.ai/rules/app.md). An error is already jarring; a bright page with a friendly figure reads as "here is what happened" rather than as a crash. There is no <html class="dark"> and no dark: variants in these templates -- re-adding them "to match the rest of the app" is the obvious wrong fix, and tests/Feature/ErrorPagesTest.php ('every error page is light mode') asserts against it. Flux's own button markup ships dark: variants that cannot be removed and are inert without a dark root class, so that test asserts on the root class plus this app's own template sources, not on the string appearing anywhere in the response.

x-errors.figure draws one whimsical SVG per status (403 padlock, 404 map pin, 419 clock face, 429 stop palm, 500 unplugged cable, 503 cog). Inline SVG, not image files: a 500 page must not depend on the storage disk or a build artefact resolving. Each is aria-hidden (the heading already says it) and every animation sits inside a prefers-reduced-motion: no-preference guard.

Two sizing traps, both cost a round trip: (1) the figures sit in the middle of their 200x160 canvas, so the viewBox is cropped to the art (28 24 144 120) -- an untrimmed box pads the page with empty space and pushes the heading below the fold; (2) the ground ellipse's y is per-status via a match(), because the drawings do not share a baseline and one shared value leaves a visible gap under the shorter figures. h-36 is the size where face detail reads without competing with the heading.

TAILWIND TRAP: a new height/class used only in a NEW template is not in public/build until `npm run build` runs. The SVG rendered at its intrinsic size and looked like the class was being ignored -- check the compiled CSS for the utility before debugging the markup.
