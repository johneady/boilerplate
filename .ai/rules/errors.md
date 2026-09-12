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
