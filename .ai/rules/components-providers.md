---
paths:
  - 'resources/views/layouts/public.blade.php, resources/views/components/business-footer.blade.php, app/Providers/AppServiceProvider.php'
---

# Components Providers

## The public layout, and how a page overrides the head description
`layouts/public.blade.php` is the shell for every public page (welcome, content pages, contact form), referenced as `<x-layouts::public>`. It was extracted from welcome.blade.php, which used to be a standalone HTML document. Unlike the signed-in shell it follows the visitor's colour scheme, so every colour needs a dark: counterpart.

A page's own SEO description reaches the head by being passed INTO the @include, not set in an @php block: `View::composer('partials.head')` runs after the view's own data is bound and would overwrite it. That composer now keeps a description already present and uses the setting only as a fallback. Setting `$seoDescription` in a template before the include does nothing.

`$footerPages` is a CLOSURE, invoked as `$footerPages()` in the footer's @foreach. The composer defers the query so the compact variant -- the auth pages' footer, which has no link row -- never runs it, keeping those pages renderable when the database is unreachable. The Contact link renders unconditionally, which is why PagesSeeder gives the contact page `show_in_footer => false` (it would otherwise print twice).

Full-page Livewire components on public routes must name this layout in render() (`->layout('layouts::public', [...])`). Livewire's default component_layout is 'layouts::app', the signed-in sidebar shell, which resolves auth()->user()->avatarUrl() and 500s for guests.
