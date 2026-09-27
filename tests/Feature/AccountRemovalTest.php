<?php

use App\Auth\AccountRemovalRefused;
use App\Auth\Role;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the last active administrator cannot be deleted, even from code', function () {
    $admin = User::factory()->admin()->create();
    // A deactivated administrator cannot run the panel, so does not count.
    User::factory()->admin()->deactivated()->create();

    expect(fn () => $admin->delete())->toThrow(fn (AccountRemovalRefused $e) => expect($e->protectsLastAdministrator())->toBeTrue());

    $this->assertModelExists($admin);
});

test('the last active administrator cannot be deactivated', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $admin->deactivate())->toThrow(AccountRemovalRefused::class);

    expect($admin->fresh()->isDeactivated())->toBeFalse();
});

test('the last active administrator cannot be demoted, even from code', function () {
    $admin = User::factory()->admin()->create();
    $admin->role = Role::Editor;

    expect(fn () => $admin->save())->toThrow(AccountRemovalRefused::class);

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('an account named on a financial record cannot be deleted', function (string $model, string $column) {
    $staff = User::factory()->role(Role::Manager)->create();
    $record = $model::factory()->create([$column => $staff->id]);

    expect(fn () => $staff->delete())->toThrow(fn (AccountRemovalRefused $e) => expect($e->protectsLastAdministrator())->toBeFalse());

    $this->assertModelExists($staff);
    expect($record->fresh()->{$column})->toBe($staff->id);
})->with([
    'a manual payment they recorded' => [Payment::class, 'recorded_by'],
    'a refund they issued' => [Refund::class, 'initiated_by'],
]);

test('the database refuses to delete an account named on a refund, whatever skips the model', function () {
    $staff = User::factory()->role(Role::Manager)->create();
    Refund::factory()->create(['initiated_by' => $staff->id]);

    expect(fn () => DB::table('users')->where('id', $staff->id)->delete())->toThrow(QueryException::class);

    $this->assertModelExists($staff);
});

test('deactivating an account ends its sessions and remember-me cookie', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->role(Role::Editor)->create(['remember_token' => 'old-token']);
    $colleague = User::factory()->create();
    foreach ([$user, $colleague] as $owner) {
        DB::table('sessions')->insert([
            'id' => 'session-'.$owner->id,
            'user_id' => $owner->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    $user->deactivate();

    expect($user->fresh())
        ->isDeactivated()->toBeTrue()
        ->remember_token->not->toBe('old-token');
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseHas('sessions', ['user_id' => $colleague->id]);
});

test('an account with a running subscription cannot be deactivated', function () {
    $subscriber = Subscription::factory()->create()->user;

    expect(fn () => $subscriber->deactivate())->toThrow(AccountRemovalRefused::class);

    expect($subscriber->fresh()->isDeactivated())->toBeFalse();
});

test('a deactivated account is signed out on its next request', function () {
    $user = User::factory()->deactivated()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This account has been deactivated.');

    $this->assertGuest();
});

test('a deactivated administrator is refused the admin panel', function () {
    User::factory()->admin()->create();

    $this->actingAs(User::factory()->admin()->deactivated()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('a deactivated administrator is denied abilities the bypass would grant', function () {
    $admin = User::factory()->admin()->deactivated()->create();

    expect($admin->can('viewAny', User::class))->toBeFalse();
});
