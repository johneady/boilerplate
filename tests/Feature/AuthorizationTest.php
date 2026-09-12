<?php

use App\Auth\Permission;
use App\Auth\Role;
use App\Models\Setting;
use App\Models\User;

test('a new user gets the least privileged role', function () {
    $user = User::create([
        'name' => 'Fresh',
        'email' => 'fresh@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()->role)->toBe(Role::DEFAULT)
        ->and($user->fresh()->is_admin)->toBeFalse();
});

test('the role cannot be mass assigned', function () {
    // The same protection is_admin had: a forged registration payload naming
    // a role must not be able to grant itself one.
    $user = User::create([
        'name' => 'Mass Assigned',
        'email' => 'mass-role@example.com',
        'password' => 'password',
        'role' => Role::Admin->value,
    ]);

    expect($user->fresh()->role)->toBe(Role::DEFAULT);
});

test('an unsaved user reads as the least privileged role', function () {
    // A null role would make every check on an unsaved model throw rather
    // than deny, canAccessPanel() included.
    $user = new User;

    expect($user->role)->toBe(Role::DEFAULT)
        ->and($user->is_admin)->toBeFalse()
        ->and($user->hasPermission(Permission::AccessAdminPanel))->toBeFalse();
});

test('is_admin stays in step with the role it derives from', function () {
    $user = User::factory()->create();

    $user->is_admin = true;
    expect($user->role)->toBe(Role::Admin)->and($user->is_admin)->toBeTrue();

    $user->is_admin = false;
    expect($user->role)->toBe(Role::DEFAULT)->and($user->is_admin)->toBeFalse();

    $user->role = Role::Admin;
    expect($user->is_admin)->toBeTrue();
});

test('an unrecognised role name fails closed rather than throwing', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasRole('not-a-role'))->toBeFalse()
        ->and($user->hasRole('admin'))->toBeTrue();
});

test('hasAnyRole matches any of the given roles', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasAnyRole([Role::User, Role::Admin]))->toBeTrue()
        ->and($user->hasAnyRole([Role::User]))->toBeFalse();
});

test('the withRole scope filters by role', function () {
    $admin = User::factory()->admin()->create();
    $plain = User::factory()->create();

    expect(User::withRole(Role::Admin)->pluck('id')->all())->toBe([$admin->getKey()])
        ->and(User::withRole([Role::User])->pluck('id')->all())->toBe([$plain->getKey()])
        ->and(User::withRole([Role::User, Role::Admin])->count())->toBe(2);
});

test('every permission is registered as a gate ability', function () {
    $admin = User::factory()->admin()->create();
    $plain = User::factory()->create();

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue()
            ->and($plain->can($permission->value))->toBeFalse();
    }
});

test('administrators pass abilities that have no policy at all', function () {
    // The Gate::before bypass, which is what keeps a model whose policy has
    // not been written yet manageable by an administrator.
    expect(User::factory()->admin()->create()->can('some-unwritten-ability'))->toBeTrue()
        ->and(User::factory()->create()->can('some-unwritten-ability'))->toBeFalse();
});

test('the administrator bypass does not override the self-protection rules', function () {
    // The whole point of exempting these abilities from Gate::before: a
    // blanket admin bypass would answer them true and undo the policy.
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('updateRole', $admin))->toBeFalse()
        ->and($admin->can('delete', $other))->toBeTrue()
        ->and($admin->can('updateRole', $other))->toBeTrue();
});

test('ordinary users cannot manage other users', function () {
    $plain = User::factory()->create();
    $other = User::factory()->create();

    expect($plain->can('viewAny', User::class))->toBeFalse()
        ->and($plain->can('view', $other))->toBeFalse()
        ->and($plain->can('create', User::class))->toBeFalse()
        ->and($plain->can('update', $other))->toBeFalse()
        ->and($plain->can('delete', $other))->toBeFalse()
        ->and($plain->can('updateRole', $other))->toBeFalse();
});

test('abilities with no mapped permission deny rather than allow', function () {
    // Users are not soft-deleted, so the policy maps neither ability; they
    // must deny an ordinary user instead of falling through to allow.
    $plain = User::factory()->create();
    $other = User::factory()->create();

    expect($plain->can('restore', $other))->toBeFalse()
        ->and($plain->can('forceDelete', $other))->toBeFalse();
});

test('panel access is decided by permission, not by a boolean column', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect(User::factory()->admin()->create()->canAccessPanel($panel))->toBeTrue()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeFalse();
});

test('the bypass exemption does not spill onto other models', function () {
    // The exemption is scoped to checks against the acting user's OWN
    // account. Keyed on the ability name alone it would stand the bypass down
    // for 'delete' on every model in the application, so anything whose
    // policy has not been written yet would start denying administrators --
    // exactly what the bypass exists to prevent.
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', new Setting))->toBeTrue()
        ->and($admin->can('delete', Setting::class))->toBeTrue()
        ->and($admin->can('forceDelete', new Setting))->toBeTrue()
        ->and($admin->can('delete', $admin))->toBeFalse();
});

test('every role badge colour is registered on the panel', function () {
    // Filament emits a colour's CSS custom properties only for colours
    // registered on the panel. A badge naming an unregistered colour still
    // gets its fi-color-* class, so it renders visibly flat with nothing in
    // the markup to explain why -- which is how the role badges first shipped.
    $registered = array_keys(Filament\Facades\Filament::getPanel('admin')->getColors());

    foreach (Role::cases() as $role) {
        expect($registered)->toContain($role->color());
    }
});
