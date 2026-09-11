---
paths:
  - 'resources/views/layouts/app/**, resources/views/components/desktop-user-menu.blade.php'
---

# Views Components

## The user avatar renders in FOUR places, not one
Each of the two user menus renders the avatar twice — the button that opens the dropdown, and the identity row inside it:

  desktop (x-desktop-user-menu): <flux:sidebar.profile :avatar> + <flux:avatar :src>
  mobile (layouts/app/sidebar.blade.php): <flux:profile :avatar> + <flux:avatar :src>

Note the prop differs: the profile components take `:avatar`, plain <flux:avatar> takes `:src`. Miss one and that menu silently falls back to initials while the other three look correct — which is exactly how the mobile header button shipped broken.

All four carry `circle` (the settings-page preview too, five in all). The processed conversions are square -- cover, 64x64 and 512x512 in config/images.php -- so a circle crops cleanly; changing a conversion to a non-square aspect would distort every one of them.

Testing trap, same shape as the blue-theme one above: asserting the avatar URL appears in the dashboard response is a FALSE PASS, because three correct renders satisfy it. Count instead — substr_count($html, 'src="/storage/...') must be 4, and so must substr_count($html, 'data-circle="true"') (tests/Feature/DashboardTest.php). Anchoring to an element does not work here either: Flux nests the <img> inside <ui-profile>, so the src is not an attribute of the element you would anchor to.
