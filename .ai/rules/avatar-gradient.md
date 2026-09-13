---
paths:
  - app/Models/User.php
  - app/Filament/Resources/Users/UserResource.php
  - app/Filament/AvatarProviders/InitialsAvatarProvider.php
---

# Avatar Gradient

## The initials gradient lives in THREE render paths and must not drift
User::avatarGradient() is the single source of the fallback avatar's colour: the Flux sites render it as an inline style via avatarGradientStyle(), and the data-URI SVG <linearGradient> comes from ONE builder, User::initialsAvatarUrl(), which both the Filament users table (through the UserResource::initialsAvatarUrl() facade) and the panel's user-menu fallback (App\Filament\AvatarProviders\InitialsAvatarProvider, registered via ->defaultAvatarProvider()) render. A user who is teal in the sidebar and orange in the admin table is a bug -- tests/Feature/UserResourceTest.php pins the paths together; keep changing them through the model, never by editing one side's markup. Filament's stock UiAvatars fallback must not come back either: it fetches initials from ui-avatars.com, leaking the user's name to a third party.

The palette const on User is APPEND-ONLY: entries are picked by crc32(key) % count, so inserting or reordering entries recolours existing users. Seed is the immutable primary key (name only for unsaved models), so renaming a user must not change their colour.

Testing trap: the profile wrappers (flux:profile, flux:sidebar.profile) forward avatar:-prefixed attributes onto the inner avatar but ALSO echo them onto their own button, because Flux renders the button before plucking. The gradient string therefore appears SIX times in the dashboard response while only FOUR avatars carry it. Count avatar elements (<div data-flux-avatar ... style="...">), not raw style substrings -- and allow the trailing ";" Blade appends to the style value.
