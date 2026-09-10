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
