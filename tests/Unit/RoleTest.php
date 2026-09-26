<?php

use App\Auth\Permission;
use App\Auth\Role;

test('the default role is the least privileged one', function () {
    expect(Role::DEFAULT)->toBe(Role::User)
        ->and(Role::DEFAULT->permissions())->toBe([]);
});

test('administrators carry every permission', function () {
    // Enumerated rather than listed in Role::permissions(), so a permission
    // added later is granted to admins instead of silently denied.
    expect(Role::Admin->permissions())->toBe(Permission::cases());

    foreach (Permission::cases() as $permission) {
        expect(Role::Admin->hasPermission($permission))->toBeTrue();
    }
});

test('ordinary users carry no permissions', function () {
    foreach (Permission::cases() as $permission) {
        expect(Role::User->hasPermission($permission))->toBeFalse();
    }
});

test('levels follow the declaration order', function () {
    expect(Role::User->level())->toBe(0)
        ->and(Role::Editor->level())->toBe(1)
        ->and(Role::Bookkeeper->level())->toBe(2)
        ->and(Role::Manager->level())->toBe(3)
        ->and(Role::Admin->level())->toBe(4);
});

test('parallel staff roles do not cover each other', function () {
    // A Bookkeeper is declared after an Editor but holds none of its grants,
    // so position must not decide this.
    expect(Role::Bookkeeper->atLeast(Role::Editor))->toBeFalse()
        ->and(Role::Editor->atLeast(Role::Bookkeeper))->toBeFalse();
});

test('every staff role works in the admin panel', function (Role $role) {
    expect($role->hasPermission(Permission::AccessAdminPanel))->toBeTrue();
})->with([Role::Editor, Role::Bookkeeper, Role::Manager]);

test('a manager can do everything an editor and a bookkeeper can', function () {
    foreach ([...Role::Editor->permissions(), ...Role::Bookkeeper->permissions()] as $permission) {
        expect(Role::Manager->hasPermission($permission))->toBeTrue();
    }
});

test('no staff role carries the permissions kept for administrators', function (Role $role, Permission $permission) {
    // Settings, credentials, plans, other people's accounts and the
    // operational trail: each lets the holder change who can do what, or
    // read what they should not, so only an administrator holds them.
    expect($role->hasPermission($permission))->toBeFalse();
})->with([Role::Editor, Role::Bookkeeper, Role::Manager])->with([
    Permission::ManageSettings,
    Permission::ManagePaymentSettings,
    Permission::ManagePlans,
    Permission::CreateUsers,
    Permission::UpdateUsers,
    Permission::DeleteUsers,
    Permission::ViewAuditLog,
    Permission::ViewLogs,
]);

test('a bookkeeper cannot move money', function (Permission $permission) {
    expect(Role::Bookkeeper->hasPermission($permission))->toBeFalse();
})->with([
    Permission::RefundPayments,
    Permission::CapturePayments,
    Permission::RecordManualPayments,
    Permission::ManagePaymentLinks,
    Permission::ManageSubscriptions,
]);

test('a role is at least itself and every role below it', function () {
    expect(Role::Admin->atLeast(Role::Admin))->toBeTrue()
        ->and(Role::Admin->atLeast(Role::Manager))->toBeTrue()
        ->and(Role::Manager->atLeast(Role::Admin))->toBeFalse()
        ->and(Role::Manager->atLeast(Role::Editor))->toBeTrue()
        ->and(Role::Manager->atLeast(Role::Bookkeeper))->toBeTrue()
        ->and(Role::Admin->atLeast(Role::User))->toBeTrue()
        ->and(Role::User->atLeast(Role::User))->toBeTrue()
        ->and(Role::User->atLeast(Role::Admin))->toBeFalse();
});

test('every role and permission declares its own display copy', function () {
    // A match() without a case for a new enum member throws rather than
    // returning a default, so this fails the moment one is added untended.
    foreach (Role::cases() as $role) {
        expect($role->label())->not->toBe('')
            ->and($role->description())->not->toBe('')
            ->and($role->color())->not->toBe('');
    }

    foreach (Permission::cases() as $permission) {
        expect($permission->label())->not->toBe('');
    }
});

test('permission values are namespaced so they cannot collide', function () {
    foreach (Permission::cases() as $permission) {
        expect($permission->value)->toContain('.');
    }
});
