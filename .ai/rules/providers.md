---
paths:
  - 'app/Auth/**, app/Policies/**, app/Concerns/HasRoles.php, app/Providers/AuthServiceProvider.php'
---

# Providers

## Authorization is role-driven; is_admin is a derived accessor, not a column
users.role (string, default App\Auth\Role::DEFAULT) is the single source of truth. There is NO is_admin column any more -- HasRoles exposes $user->is_admin as an Attribute that reads the role and, when assigned, writes it. That accessor exists only so the ~40 pre-existing call sites (welcome.blade.php, ResolvesLoginRedirect, DevLoginAccounts, seeders, tests) kept working; new code should ask hasPermission()/hasRole() instead. Assigning is_admin = false demotes an Admin to Role::DEFAULT and leaves any other role alone (a Manager stays a Manager); move someone between the non-admin roles by setting the role explicitly.

## The staff roles are parallel, not a chain
Editor, Bookkeeper and Manager sit between User and Admin in declaration order, but a Bookkeeper holds none of an Editor's grants. Role::atLeast() / hasRoleAtLeast() therefore compare permission sets, not level(); level() is an ordering for display only. Better still, check a permission.

## BasePolicy must answer every ability Filament asks
Outside strict mode Filament ALLOWS an ability whose policy method is missing. That was harmless while only admins (who pass Gate::before) reached the panel; with staff roles it opened bulk delete and reordering to anyone who could view a resource. BasePolicy therefore defines deleteAny/restoreAny/forceDeleteAny/reorder/replicate (borrowing the single-record ability's permission) and the relation-manager abilities (denied unless mapped). Filament's DeleteBulkAction does not authorize each selected record, so the class-level deleteAny check is the only guard. tests/Feature/StaffRolesTest.php covers both holes.

Check permissions, never role names: `$user->can(Permission::X->value)` or hasPermission(). Role::Admin->permissions() returns Permission::cases() by enumeration, so a new permission is granted to admins rather than silently denied.

Gate::before (AuthServiceProvider) passes admins through everything EXCEPT the abilities in SELF_PROTECTED_ABILITIES (delete, forceDelete, updateRole). Those are the rules that deny an administrator on purpose -- self-deletion and self-demotion -- and Gate::before short-circuits on any non-null return, so adding one there would silently undo UserPolicy. Add new self-protecting abilities to that list too. Policies are registered explicitly in POLICIES rather than by convention discovery, so a renamed policy fails loudly instead of falling through to the admin bypass (which looks like it works while testing as an admin).

BasePolicy denies any ability with no mapped permission, rather than allowing. A new User must be given a role before role checks are meaningful: User::$attributes defaults it, because the DB default only applies on INSERT and a null role would throw instead of denying on an unsaved model.

tests/Feature/AuthorizationTest.php and tests/Unit/RoleTest.php cover all of this.
