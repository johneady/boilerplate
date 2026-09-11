---
paths:
  - resources/css/filament/admin/theme.css
---

# Admin

## Tailwind classes in panel views need the custom Filament theme
Filament's default stylesheet only contains the utilities Filament's own UI uses. Arbitrary Tailwind classes written in app/Filament/** or resources/views/filament/** silently render unstyled without a custom theme — no error, just a plain page.

resources/css/filament/admin/theme.css is that theme. It is registered via ->viteTheme() in AdminPanelProvider and listed in vite.config.js input. Its @source lines are what pull classes out of app/Filament/** and resources/views/filament/**; adding panel Blade outside those paths means new classes will not compile.

resources/css/app.css is the Flux/frontend stylesheet and is NOT loaded in the panel. Do not expect its @theme tokens (--color-accent, the zinc overrides) to apply to admin pages.

After editing panel Blade, run `npm run build` (or have `npm run dev` running) or the new classes will be missing from the compiled theme.

## Flux components in the panel need flux.css imported and flux.js render-hooked
The panel hosts Flux-built Livewire components (the Account cluster), and the Filament layout is not the Flux app layout, so it ships none of Flux's assets by default. Two separate pieces are required, and each fails silently on its own:

CSS -- theme.css imports vendor/livewire/flux/dist/flux.css and @sources the flux stubs plus resources/views/partials/settings/**. Without it Flux controls render as unstyled HTML: bare "Choose file" inputs, invisible buttons. app.css is NOT loaded in the panel. Only free livewire/flux is installed, so do not add a flux-pro @source -- the path does not exist.

JS -- AdminPanelProvider render-hooks BODY_END with a <flux:toast.group> plus @fluxScripts. Without the scripts the markup looks right but modals, dropdowns and the avatar picker are inert; without the toast group every Flux::toast() in the hosted components is dropped, so a saved password confirms nothing.

## Dark mode in the panel belongs to Filament, never @fluxAppearance
Do not add @fluxAppearance to the panel. Filament has its own theme switcher keyed on localStorage['theme']; Flux's is keyed on flux.appearance and its init runs afterwards. With no Flux key set it resolves to 'system' and strips html.dark, so a Dark choice made in Filament's switcher is silently undone on the next page load.

This is why the Account cluster has no Appearance page: it would be a second control for a setting Filament already owns. resources/views/livewire/settings/appearance.blade.php still serves the non-Filament /settings/appearance, which is Flux's own layout and unaffected.

All of the above is covered by tests/Feature/AccountClusterTest.php. Rebuild (npm run build) after touching panel Blade or the new classes are missing from the compiled theme.
