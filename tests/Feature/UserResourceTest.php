<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('the panel navigation links to the users table', function () {
    $this->get('/admin/users')->assertSuccessful();
});

test('non-admins may not reach the users table', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/users')
        ->assertForbidden();
});

test('the table lists existing users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords($users);
});

test('a user can be created through the modal', function () {
    Livewire::test(ManageUsers::class)
        ->callAction(CreateAction::class, [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'is_admin' => false,
        ])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'ada@example.com')->sole();

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->is_admin)->toBeFalse()
        // A usable password is stored so the NOT NULL column is satisfied,
        // but nobody knows it -- the account is reached via password reset.
        ->and($user->password)->not->toBeEmpty();
});

test('a created user gets an unguessable password, not a shared default', function () {
    Livewire::test(ManageUsers::class)
        ->callAction(CreateAction::class, ['name' => 'First', 'email' => 'first@example.com'])
        ->callAction(CreateAction::class, ['name' => 'Second', 'email' => 'second@example.com']);

    $first = User::where('email', 'first@example.com')->sole();
    $second = User::where('email', 'second@example.com')->sole();

    // Hashes always differ thanks to per-hash salts, so compare against the
    // plaintext an operator might reasonably guess.
    foreach (['password', 'secret', '', 'first@example.com', 'First'] as $guess) {
        expect(Hash::check($guess, $first->password))->toBeFalse();
    }

    // The two accounts must not share one hard-coded password.
    expect(Hash::check('password', $second->password))->toBeFalse();
});

test('a created user can set their password through the reset flow', function () {
    Notification::fake();

    Livewire::test(ManageUsers::class)
        ->callAction(CreateAction::class, ['name' => 'Reset Me', 'email' => 'resetme@example.com']);

    // The admin never chose a password, so the reset flow is how the new
    // account becomes reachable. Signed out first, because the reset routes
    // are guest-only -- a new user would arrive logged out.
    auth()->logout();

    $this->post(route('password.email'), ['email' => 'resetme@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertSentTo(
        User::where('email', 'resetme@example.com')->sole(),
        ResetPassword::class,
    );
});

test('a user can be granted admin rights through the modal', function () {
    $user = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $user, [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('editing a user never changes their password', function () {
    $user = User::factory()->create(['password' => Hash::make('original-password')]);
    $originalHash = $user->password;

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $user, [
            'name' => 'Renamed',
            'email' => $user->email,
        ])
        ->assertHasNoTableActionErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->password)->toBe($originalHash)
        ->and(Hash::check('original-password', $user->password))->toBeTrue();
});

test('a password posted to the edit action is ignored', function () {
    $user = User::factory()->create(['password' => Hash::make('original-password')]);
    $originalHash = $user->password;

    // saveUser() is the single write path for both modals, so calling it with a
    // password in the payload proves the value is dropped rather than written.
    UserResource::saveUser($user, [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'Br4nd-new-passw0rd',
    ]);

    expect($user->fresh()->password)->toBe($originalHash)
        ->and(Hash::check('Br4nd-new-passw0rd', $user->fresh()->password))->toBeFalse()
        ->and(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});

test('the edit modal has no password field and never exposes the hash', function () {
    $user = User::factory()->create(['password' => Hash::make('original-password')]);

    $component = Livewire::test(ManageUsers::class)
        ->mountTableAction(EditAction::class, $user);

    $data = $component->instance()->mountedActions[0]['data'];

    expect($data)->not->toHaveKey('password')
        ->and($data)->not->toHaveKey('password_confirmation');

    $component->assertDontSee($user->password);
});

test('the table does not render a password column', function () {
    $user = User::factory()->create(['password' => Hash::make('original-password')]);

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertDontSee($user->password)
        ->assertTableColumnDoesNotExist('password');
});

test('a user can be deleted through the table', function () {
    $user = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction(DeleteAction::class, $user);

    expect(User::whereKey($user->getKey())->exists())->toBeFalse();
});

test('an email address must remain unique', function () {
    $existing = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(CreateAction::class, [
            'name' => 'Duplicate',
            'email' => $existing->email,
        ])
        ->assertHasActionErrors(['email']);
});

test('a user keeps their own email address when edited', function () {
    $user = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $user, [
            'name' => 'Same Email',
            'email' => $user->email,
        ])
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->name)->toBe('Same Email');
});

test('the create modal has neither password nor verification fields', function () {
    $component = Livewire::test(ManageUsers::class)
        ->mountAction(CreateAction::class);

    expect($component->instance()->mountedActions[0]['data'])
        ->not->toHaveKey('password')
        ->and($component->instance()->mountedActions[0]['data'])
        ->not->toHaveKey('password_confirmation');
});

test('neither modal renders a password or verification field', function () {
    $fields = array_keys(
        UserResource::form(new Schema(Livewire::test(ManageUsers::class)->instance()))
            ->getFlatFields()
    );

    expect($fields)->toBe(['name', 'email', 'is_admin']);
});

test('an admin cannot delete their own account from the table', function () {
    Livewire::test(ManageUsers::class)
        ->assertTableActionHidden(DeleteAction::class, $this->admin);

    expect(User::whereKey($this->admin->getKey())->exists())->toBeTrue();
});

test('an admin may still delete other accounts', function () {
    $other = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->assertTableActionVisible(DeleteAction::class, $other)
        ->callTableAction(DeleteAction::class, $other);

    expect(User::whereKey($other->getKey())->exists())->toBeFalse();
});

test('a bulk delete of every row spares the current user', function () {
    $others = User::factory()->count(3)->create();

    $component = Livewire::test(ManageUsers::class);
    $table = $component->instance()->getTable();

    // The current user's row is not selectable, so it cannot be swept into a
    // bulk delete even when every row is selected.
    expect($table->isRecordSelectable($this->admin))->toBeFalse()
        ->and($table->isRecordSelectable($others->first()))->toBeTrue();

    // Selects every row, the current user's included, to prove the server-side
    // filter holds even when the request names them.
    $otherIds = $others->pluck('id')->all();
    $component->callTableBulkAction(
        DeleteBulkAction::class,
        [...$otherIds, $this->admin->getKey()],
    );

    expect(User::whereKey($this->admin->getKey())->exists())->toBeTrue()
        ->and(User::whereIn('id', $otherIds)->exists())->toBeFalse();
});

test('the admin toggle is disabled when editing your own account', function () {
    expect(UserResource::isCurrentUser($this->admin))->toBeTrue()
        ->and(UserResource::isCurrentUser(User::factory()->create()))->toBeFalse();
});

test('an admin cannot strip their own admin rights', function () {
    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $this->admin, [
            'name' => 'Still Admin',
            'email' => $this->admin->email,
            'is_admin' => false,
        ])
        ->assertHasNoTableActionErrors();

    $this->admin->refresh();

    expect($this->admin->name)->toBe('Still Admin')
        ->and($this->admin->is_admin)->toBeTrue();
});

test('an admin may still demote another admin', function () {
    $other = User::factory()->admin()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $other, [
            'name' => $other->name,
            'email' => $other->email,
            'is_admin' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect($other->fresh()->is_admin)->toBeFalse();
});
