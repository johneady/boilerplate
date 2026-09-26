<?php

namespace App\Filament\Resources\Users;

use App\Auth\Role;
use App\Filament\Exports\UserExporter;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Media\MediaCollection;
use App\Models\User;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only, and only while editing: creation has no record to
                // show one for, and an admin does not upload another person's
                // photo -- every avatar write goes through MediaManager on the
                // owner's own profile page.
                ImageEntry::make('avatar')
                    ->label(__('users.fields.avatar'))
                    ->hiddenOn('create')
                    ->getStateUsing(fn (User $record): ?string => static::avatarState($record, 'full'))
                    ->circular()
                    ->imageSize(96)
                    ->defaultImageUrl(fn (User $record): string => static::initialsAvatarUrl($record)),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Lowercased as User stores it, so the unique rule
                    // compares like with like on case-sensitive databases.
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => $state === null ? null : Str::lower($state))
                    ->unique(ignoreRecord: true),
                // An admin editing themselves cannot drop their own rights:
                // doing so would lock them out of this panel on save, with no
                // way back in short of re-seeding or another admin's help.
                // UserPolicy::updateRole() is the same rule where the Gate can
                // see it, and saveUser() enforces it again server side.
                Select::make('role')
                    ->label(__('users.fields.role'))
                    ->options(fn (): array => collect(Role::cases())
                        ->mapWithKeys(fn (Role $role): array => [$role->value => __($role->label())])
                        ->all())
                    ->default(Role::DEFAULT->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->disabled(fn (?User $record): bool => static::isCurrentUser($record))
                    ->helperText(fn (?User $record, $state): string => static::isCurrentUser($record)
                        ? __('users.own_role_locked')
                        : static::describeRole($state, $record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Only the avatar collection is eager-loaded, because the avatar
            // column below resolves through avatarUrl() -> getMedia(), which
            // would otherwise run one media query per row of this table.
            // getMedia() filters the loaded relation in memory, and skipping
            // the other collections keeps the eager load to one row per user.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(
                ['media' => fn ($media) => $media->inCollection(MediaCollection::Avatar)],
            ))
            ->columns([
                // State is resolved through User::avatarUrl() rather than from
                // the media row directly: the row's `path` holds the conversion
                // DIRECTORY, not a file, and the accessor is what resolves the
                // named conversion. It also returns null while processing is
                // still in flight and when the conversion is missing, which is
                // what makes the initials fallback below correct rather than a
                // broken image.
                ImageColumn::make('avatar')
                    ->label(__('users.fields.avatar'))
                    ->getStateUsing(fn (User $record): ?string => static::avatarState($record))
                    ->circular()
                    // The cell is wrapped in the row-click button, whose only
                    // content is this image -- so without an alt the button has
                    // no accessible name at all (axe: button-name, critical).
                    // The user's name is what the button opens, so it is the
                    // right name for both.
                    ->alt(fn (User $record): string => $record->name)
                    ->defaultImageUrl(fn (User $record): string => static::initialsAvatarUrl($record)),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label(__('users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => __($state->label()))
                    ->color(fn (Role $state): string => $state->color())
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label(__('users.fields.verified'))
                    ->boolean()
                    ->sortable(),
                // Formatted through the settings service rather than
                // ->dateTime(): the Registered column is a wall-clock date
                // for an administrator, so it follows the display timezone,
                // format and locale settings the rest of the site does.
                TextColumn::make('created_at')
                    ->label(__('users.fields.registered'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label(__('users.fields.role'))
                    ->options(fn (): array => collect(Role::cases())
                        ->mapWithKeys(fn (Role $role): array => [$role->value => __($role->label())])
                        ->all()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('users.fields.email_verified'))
                    ->nullable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label(__('users.export'))
                    ->exporter(UserExporter::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (User $record, array $data): User => static::saveUser($record, $data)),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => static::isCurrentUser($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // The current user is filtered out of the selection server
                    // side, not merely made unselectable in the UI, so a forged
                    // request naming their own ID still cannot delete them.
                    DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records
                            ->reject(fn (Model $record): bool => $record instanceof User
                                && static::isCurrentUser($record))
                            ->each->delete()),
                ]),
            ])
            // The current user's row cannot be selected at all, so even
            // "select all" across pages cannot sweep the account performing
            // the deletion into a bulk delete.
            ->checkIfRecordIsSelectableUsing(
                fn (User $record): bool => ! static::isCurrentUser($record),
            );
    }

    /**
     * One avatar conversion as an absolute URL, or null when there is no
     * uploaded photo to show.
     *
     * Shared by the form entry and the table column. The state is passed
     * through url() because ImageEntry and ImageColumn treat anything that is
     * not already an absolute URL as a path on their own disk: avatarUrl()
     * returns a root-relative "/storage/..." string, which would otherwise be
     * looked up as a FILE of that name, silently fail the existence check,
     * and fall back to initials for every user who has an avatar. Null still
     * falls through to the initials fallback below, as it does while
     * processing is in flight.
     */
    public static function avatarState(User $record, string $conversion = 'thumb'): ?string
    {
        return filled($url = $record->avatarUrl($conversion)) ? url($url) : null;
    }

    /**
     * Build a data-URI initials avatar for a user with no uploaded photo.
     *
     * A facade over User::initialsAvatarUrl(), kept because the table's
     * defaultImageUrl() and the tests speak this name; the markup itself
     * lives on the model so every data-URI render path shares one builder.
     */
    public static function initialsAvatarUrl(User $user): string
    {
        return $user->initialsAvatarUrl();
    }

    /**
     * Describe the role currently chosen in the form.
     *
     * Resolved with tryFrom() and a fallback rather than Role::from(): the
     * state comes from the live form, so it is whatever the browser last
     * sent. An unrecognised value, an empty string from a cleared select, or
     * a non-scalar from a tampered payload would all make from() throw a
     * ValueError -- an unhandled 500 while merely rendering a help line.
     * Falling back to the record's stored role (and then to the default)
     * keeps the copy sensible instead.
     */
    public static function describeRole(mixed $state, ?User $record): string
    {
        $role = is_string($state) ? Role::tryFrom($state) : null;

        // The record's own role stands in while the form holds nothing
        // usable; creation has no record at all, so that falls to the default.
        $role ??= $record instanceof User ? $record->role : Role::DEFAULT;

        return __($role->description());
    }

    /**
     * Determine whether a record is the signed-in user's own account.
     *
     * Null during creation, where there is no record to compare against yet.
     */
    public static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->getKey() === auth()->id();
    }

    /**
     * Persist the modal form's data onto a user.
     *
     * The role is not mass-assignable (see the User model's Fillable
     * attribute), so it is assigned as a property here -- the same way
     * AdminUserSeeder sets it.
     *
     * Passwords are not part of this form. A new user gets an unguessable
     * random one so the NOT NULL column is satisfied without anybody, the
     * creating admin included, knowing a working credential; the account is
     * reached by way of the "forgot password" flow. An existing user's
     * password is never touched here.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveUser(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! $user->exists) {
            $user->password = Str::password(32);
        }
        // Enforced here as well as on the disabled select: a disabled field is
        // dropped from the payload, so without this an operator could still
        // demote themselves by posting a role directly.
        //
        // An absent or unrecognised role leaves the user's current role
        // alone, and only a brand new user falls back to the default. Reading
        // it as "demote to the default" instead would silently strip an
        // administrator's rights on any payload that happened to omit the
        // field -- a privilege change nobody asked for and nothing reports.
        if (! static::isCurrentUser($user)) {
            $submitted = $data['role'] ?? null;

            $user->role = (is_string($submitted) ? Role::tryFrom($submitted) : null)
                ?? ($user->exists ? $user->role : Role::DEFAULT);
        }

        $user->save();

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
