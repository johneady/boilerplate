---
paths:
  - 'app/Filament/Clusters/Account/**'
---

# Account

## Account settings pages host the Livewire settings components, not copies
The Account cluster (/admin/account/*) exists so the user menu's profile link keeps admins inside the panel instead of dropping them onto the Flux-chromed /settings pages. Both routes stay live and render the SAME App\Livewire\Settings\* components -- never fork the markup.

Each component picks its view through App\Concerns\RendersSettingsChrome: mounted with `bare => true` (what the Filament pages pass) it renders resources/views/partials/settings/<name>.blade.php alone; otherwise it renders livewire/settings/<name>.blade.php, which wraps that same partial in the Flux heading and navlist. So page content belongs in the partial -- putting it in the livewire view makes it vanish from the panel.

Each component returns both view names as literal strings (bareView()/chromedView(), both @return view-string). Do not "simplify" these back into a concatenated name in the trait: PHPStan runs at level 8 and cannot verify a built-up view name, so a typo would become a runtime "view not found" instead of a static error.

$bare is #[Locked] on purpose: which chrome renders is the host page's call, not something a crafted update request may flip.

Each partial needs exactly ONE root element. Livewire throws MultipleRootElementsDetectedException otherwise, and the Flux view's wrapper is not inherited when rendering bare.

Security reapplies password.confirm via $routeMiddleware -- drop it and the panel becomes a second, ungated route to 2FA disable and passkey deletion.
