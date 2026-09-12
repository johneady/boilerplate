---
paths:
  - 'app/Auth/**, app/Policies/**, app/Concerns/HasRoles.php, app/Providers/AuthServiceProvider.php'
---

# Providers

## Authorization is role-driven; is_admin is a derived accessor, not a column
users.role (string, default App\Auth\Role::DEFAULT) is the single source of truth. There is NO is_admin column any more -- HasRoles exposes $user->is_admin as an Attribute that reads the role and, when assigned, writes it. That accessor exists only so the ~40 pre-existing call sites (welcome.blade.php, ResolvesLoginRedirect, DevLoginAccounts, seeders, tests) kept working; new code should ask hasPermission()/hasRole() instead. Assigning is_admin = false demotes to Role::DEFAULT, which is only well-defined while there are two roles -- once a third exists, set the role explicitly.

Check permissions, never role names: `$user->can(Permission::X->value)` or hasPermission(). Role::Admin->permissions() returns Permission::cases() by enumeration, so a new permission is granted to admins rather than silently denied.

Gate::before (AuthServiceProvider) passes admins through everything EXCEPT the abilities in SELF_PROTECTED_ABILITIES (delete, forceDelete, updateRole). Those are the rules that deny an administrator on purpose -- self-deletion and self-demotion -- and Gate::before short-circuits on any non-null return, so adding one there would silently undo UserPolicy. Add new self-protecting abilities to that list too. Policies are registered explicitly in POLICIES rather than by convention discovery, so a renamed policy fails loudly instead of falling through to the admin bypass (which looks like it works while testing as an admin).

BasePolicy denies any ability with no mapped permission, rather than allowing. A new User must be given a role before role checks are meaningful: User::$attributes defaults it, because the DB default only applies on INSERT and a null role would throw instead of denying on an unsaved model.

tests/Feature/AuthorizationTest.php and tests/Unit/RoleTest.php cover all of this.
