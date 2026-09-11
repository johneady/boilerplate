---
paths:
  - resources/views/components/desktop-user-menu.blade.php
---

# Components

## Theming flux:menu needs ! overrides, and menu.separator's class misses the line
flux:menu, menu.item and sidebar.profile hardcode zinc (bg-white/dark:bg-zinc-700, data-active:bg-zinc-50, hover:bg-zinc-800/5). Tailwind emits those in source order, so a plain class is a coin flip — the blue overrides carry `!` deliberately. Don't "tidy" them away.

flux:menu.separator applies its class to a wrapper div; the visible line is a nested flux:separator with its own colour. Passing a bg to menu.separator does nothing. Use the wrapper markup (-mx-[.3125rem] my-[.3125rem] h-px) with flux:separator inside.

The user menu exists twice — x-desktop-user-menu and inline in layouts/app/sidebar.blade.php's mobile header. Change both; tests/Feature/DashboardTest.php asserts on both ui-menu panels. Match `<ui-menu\s` when counting panels: ui-menu-radio-group also carries a data-flux-menu* attribute.
