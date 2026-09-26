<?php

use App\Auth\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Media;
use App\Models\User;
use App\Settings\Settings;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Schema;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * The avatar entry from the edit form, resolved against one user.
 *
 * The entry derives its state from the record rather than from form data, so
 * there is nothing in mountedActions[0]['data'] to assert on, and Filament
 * does not render the modal body until it is opened in the browser. Building
 * the schema is what actually exercises the closures under test.
 */
function avatarEntry(User $user): ImageEntry
{
    $schema = UserResource::form(new Schema(Livewire::test(ManageUsers::class)->instance()))
        ->operation('edit')
        ->record($user);

    $entry = collect($schema->getFlatComponents())
        ->first(fn ($component): bool => $component instanceof ImageEntry);

    expect($entry)->toBeInstanceOf(ImageEntry::class);

    return $entry;
}

/**
 * The URL the avatar entry resolves to for one user.
 */
function avatarEntryState(User $user): ?string
{
    return avatarEntry($user)->getState();
}

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

test('the table reads avatars in a fixed number of media queries, however many users', function (int $users) {
    Storage::fake('public');

    User::factory()->count($users)->create()->each(fn (User $user) => Media::factory()->create([
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]));

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        // Eager loads of the avatar relation, not the site-logo lookup,
        // which runs without a model on every admin page.
        if (str_contains($query->sql, '"media"."model_id" in') || str_contains($query->sql, '`media`.`model_id` in')) {
            $queries++;
        }
    });

    Livewire::test(ManageUsers::class)->assertSuccessful();

    // One eager load for the page's avatars and one for the cross-page
    // select-all count (Filament materializes every record to filter the
    // current user out of it) -- never one lookup per row behind the
    // avatar column.
    expect($queries)->toBe(2);
})->with([3, 30]);

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
            'role' => Role::Admin->value,
        ])
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->is_admin)->toBeTrue()
        ->and($user->fresh()->role)->toBe(Role::Admin);
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

    expect($fields)->toBe(['name', 'email', 'role']);
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

test('the role select is disabled when editing your own account', function () {
    expect(UserResource::isCurrentUser($this->admin))->toBeTrue()
        ->and(UserResource::isCurrentUser(User::factory()->create()))->toBeFalse();
});

test('an admin cannot strip their own admin rights', function () {
    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $this->admin, [
            'name' => 'Still Admin',
            'email' => $this->admin->email,
            'role' => Role::User->value,
        ])
        ->assertHasNoTableActionErrors();

    $this->admin->refresh();

    expect($this->admin->name)->toBe('Still Admin')
        ->and($this->admin->is_admin)->toBeTrue()
        ->and($this->admin->role)->toBe(Role::Admin);
});

test('an admin may still demote another admin', function () {
    $other = User::factory()->admin()->create();

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $other, [
            'name' => $other->name,
            'email' => $other->email,
            'role' => Role::User->value,
        ])
        ->assertHasNoTableActionErrors();

    expect($other->fresh()->is_admin)->toBeFalse()
        ->and($other->fresh()->role)->toBe(Role::User);
});

test('the table shows an uploaded avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->giveAvatar($user, directory: 'avatars/7/abc');

    // ImageColumn only passes state straight through when it is an absolute
    // URL; a root-relative one is treated as a path on its own disk, fails the
    // existence check, and silently falls back to initials for every user who
    // actually has an avatar.
    Livewire::test(ManageUsers::class)
        ->assertSee('avatars/7/abc/thumb.webp', escape: false);
});

test('the edit modal shows the user\'s avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->giveAvatar($user, directory: 'avatars/9/def');

    // 'full' rather than the table's 'thumb': the modal renders it large.
    // Asserted as an ABSOLUTE url, because ImageEntry has the same trap
    // ImageColumn does -- it only passes state through when it is a valid URL,
    // so a root-relative "/storage/..." one would be looked up as a path on
    // its own disk and silently fall back to initials.
    expect(avatarEntryState($user))
        ->toBe(url('/storage/avatars/9/def/full.webp'));
});

test('the edit modal falls back to the initials avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $entry = avatarEntry($user);

    // No avatar means no state at all, which is what makes the default below
    // the thing that renders rather than a broken image.
    expect($entry->getState())->toBeNull()
        ->and($entry->getDefaultImageUrl())->toBe($user->initialsAvatarUrl());
});

test('the create modal has no avatar, having no record to show one for', function () {
    $fields = array_keys(
        UserResource::form(new Schema(Livewire::test(ManageUsers::class)->instance()))
            ->operation('create')
            ->getFlatComponents()
    );

    expect($fields)->not->toContain('avatar');
});

test('the create modal does not offer to create another', function () {
    $action = Livewire::test(ManageUsers::class)
        ->instance()
        ->getAction('create');

    expect($action->canCreateAnother())->toBeFalse();
});

test('the table falls back to initials when a user has no avatar', function () {
    Storage::fake('public');

    User::factory()->create(['name' => 'Ada Lovelace']);

    Livewire::test(ManageUsers::class)
        ->assertSee('data:image/svg+xml;base64,', escape: false);
});

test('the fallback avatar embeds the same gradient as the Flux sites', function () {
    $user = User::factory()->create();

    $svg = base64_decode(
        substr(UserResource::initialsAvatarUrl($user), strlen('data:image/svg+xml;base64,')),
    );

    $gradient = $user->avatarGradient();

    // This is the test that catches the two paths drifting apart: the SVG and
    // the inline style must derive from the same palette, or a user is teal in
    // the sidebar and orange in the admin table.
    expect($svg)->toContain($gradient['from'])
        ->and($svg)->toContain($gradient['to'])
        // White initials, matching the text-white overlay on the Flux sites.
        ->and($svg)->toContain('fill="#ffffff"');

    // The gradient id is unique per user, so several rows in one table page
    // cannot reference each other's stops if the SVG is ever inlined.
    expect($svg)->toContain('id="grad-'.$user->getKey().'"');
});

test('the panel user menu falls back to the same gradient initials avatar', function () {
    $user = User::factory()->create();

    // What Filament renders beside the user's name when no photo has been
    // uploaded. It must be the table's avatar verbatim -- Filament's default
    // fallback is a flat near-black circle fetched from ui-avatars.com, which
    // is both unlike every other avatar in the application and a leak of the
    // user's name to a third party.
    expect(Filament\Facades\Filament::getUserAvatarUrl($user))
        ->toBe($user->initialsAvatarUrl())
        ->toStartWith('data:image/svg+xml;base64,')
        ->not->toContain('ui-avatars.com');

    // And on the rendered page, where the user menu shows it.
    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('data:image/svg+xml;base64,', escape: false);
});

test('initials in the fallback avatar are escaped', function () {
    // Initials come from the user-supplied name, so a name beginning with "<"
    // would otherwise break out of the SVG <text> element.
    $user = User::factory()->create(['name' => '<script>alert(1)</script> Evil']);

    $svg = base64_decode(
        substr(UserResource::initialsAvatarUrl($user), strlen('data:image/svg+xml;base64,')),
    );

    expect($svg)->toContain('&lt;')
        ->and($svg)->not->toContain('<script>');

    // Still well-formed, so the browser renders it rather than discarding it.
    expect(simplexml_load_string($svg))->not->toBeFalse();
});

test('an omitted role leaves an existing user\'s role alone', function () {
    // Reading a missing field as "demote to the default" would silently strip
    // an administrator's rights on any payload that happened to omit it.
    $other = User::factory()->admin()->create();

    UserResource::saveUser($other, ['name' => 'Kept', 'email' => $other->email]);

    expect($other->fresh()->role)->toBe(Role::Admin)
        ->and($other->fresh()->name)->toBe('Kept');
});

test('an unrecognised role leaves an existing user\'s role alone', function () {
    $other = User::factory()->admin()->create();

    UserResource::saveUser($other, [
        'name' => $other->name,
        'email' => $other->email,
        'role' => 'not-a-role',
    ]);

    expect($other->fresh()->role)->toBe(Role::Admin);
});

test('a new user with no usable role gets the default', function () {
    $user = UserResource::saveUser(new User, [
        'name' => 'Brand New',
        'email' => 'brand-new@example.com',
        'role' => 'not-a-role',
    ]);

    expect($user->fresh()->role)->toBe(Role::DEFAULT);
});

test('the registered column renders through the locale and time settings', function () {
    app(Settings::class)->setMany([
        'timezone' => 'Australia/Sydney',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
    ]);

    // A fixed instant rather than now(): the assertion pins the UTC-to-Sydney
    // conversion (00:30 UTC is 11:30 AEDT in January) as well as the format.
    $user = User::factory()->create(['created_at' => '2026-01-15 00:30:00']);

    $column = Livewire::test(ManageUsers::class)->instance()->getTable()->getColumn('created_at');

    expect($column?->formatState($user->created_at))->toBe('15/01/2026, 11:30');
});

test('the role help text survives a tampered or cleared select', function () {
    // The help line is rendered from live form state, so it is whatever the
    // browser last sent. Role::from() on that throws a ValueError -- an
    // unhandled 500 while merely rendering a help line.
    $record = User::factory()->create();

    foreach (['not-a-role', '', null, ['an', 'array']] as $state) {
        expect(fn () => UserResource::describeRole($state, $record))->not->toThrow(Throwable::class);
    }

    expect(UserResource::describeRole('admin', $record))->toBe(Role::Admin->description())
        ->and(UserResource::describeRole('', $record))->toBe($record->role->description())
        ->and(UserResource::describeRole('', null))->toBe(Role::DEFAULT->description());
});
