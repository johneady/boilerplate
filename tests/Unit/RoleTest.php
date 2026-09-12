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
        ->and(Role::Admin->level())->toBe(1);
});

test('a role is at least itself and every role below it', function () {
    expect(Role::Admin->atLeast(Role::Admin))->toBeTrue()
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
