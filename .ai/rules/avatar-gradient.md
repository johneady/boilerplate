---
paths:
  - app/Models/User.php
  - app/Filament/Resources/Users/UserResource.php
---

# Avatar Gradient

## The initials gradient lives in TWO render paths and must not drift
User::avatarGradient() is the single source of the fallback avatar's colour: the Flux sites render it as an inline style via avatarGradientStyle(), and the Filament users table embeds the same two hex stops in a data-URI SVG <linearGradient> (UserResource::initialsAvatarUrl()). A user who is teal in the sidebar and orange in the admin table is a bug -- tests/Feature/UserResourceTest.php pins the two paths together; keep changing them through the model, never by editing one side's markup.

The palette const on User is APPEND-ONLY: entries are picked by crc32(key) % count, so inserting or reordering entries recolours existing users. Seed is the immutable primary key (name only for unsaved models), so renaming a user must not change their colour.

Testing trap: the profile wrappers (flux:profile, flux:sidebar.profile) forward avatar:-prefixed attributes onto the inner avatar but ALSO echo them onto their own button, because Flux renders the button before plucking. The gradient string therefore appears SIX times in the dashboard response while only FOUR avatars carry it. Count avatar elements (<div data-flux-avatar ... style="...">), not raw style substrings -- and allow the trailing ";" Blade appends to the style value.
