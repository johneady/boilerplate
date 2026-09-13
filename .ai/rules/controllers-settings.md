---
paths:
  - 'app/Http/Controllers/MediaController.php, app/Settings/Settings.php'
---

# Controllers Settings

## Private files need signature AND policy; logoMedia() must survive a dead database
MediaController checks TWO independent things and both are load-bearing. The `signed` middleware proves the link came from us and has not expired; the owning model's policy proves this user may see it. Signature alone is a bearer token (a link pasted in Slack hands over the file); policy alone means permanent URLs.

The exception is a file on the requester's OWN user record: UserPolicy::view is the admin-panel "may you browse accounts" permission, so inheriting it would deny every ordinary user their own uploads. Owning the record is checked before the policy, not through it. An ownerless file is refused outright — nothing to inherit from must not default open.

Settings::logoMedia() catches QueryException via isConnectionFailure(), same contract as the settings table itself. The head asks for the logo on EVERY page including the 500 page, so an unreachable database would otherwise mean no error page renders at all. ErrorPagesTest catches this.

Settings memoises the logo row (the head asks 3x per page) and is bound scoped, so the instance outlives an action that changes the row. Any action that uploads or removes the logo MUST call forgetLogo(), or the page re-renders showing the logo just deleted.
