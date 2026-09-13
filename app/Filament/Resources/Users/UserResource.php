<?php

namespace App\Filament\Resources\Users;

use App\Auth\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
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
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // An admin editing themselves cannot drop their own rights:
                // doing so would lock them out of this panel on save, with no
                // way back in short of re-seeding or another admin's help.
                // UserPolicy::updateRole() is the same rule where the Gate can
                // see it, and saveUser() enforces it again server side.
                Select::make('role')
                    ->label(__('users.fields.role'))
                    ->options(fn (): array => collect(Role::cases())
                        ->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])
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
            ->columns([
                // State is resolved through User::avatarUrl() rather than from
                // the avatar_path column directly: that column holds the
                // conversion DIRECTORY, not a file, and the accessor is what
                // appends the conversion name and configured format. It also
                // returns null when the conversion is missing, which is what
                // makes the initials fallback below correct rather than a
                // broken image.
                //
                // The state is passed through url() because ImageColumn treats
                // anything that is not already an absolute URL as a path on its
                // own disk. avatarUrl() returns a root-relative "/storage/..."
                // string, which would otherwise be looked up as a FILE of that
                // name, silently fail the existence check, and fall back to
                // initials for every user who has an avatar.
                ImageColumn::make('avatar')
                    ->label(__('users.fields.avatar'))
                    ->getStateUsing(fn (User $record): ?string => filled($url = $record->avatarUrl())
                        ? url($url)
                        : null)
                    ->circular()
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
                    ->formatStateUsing(fn (Role $state): string => $state->label())
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
                        ->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])
                        ->all()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('users.fields.email_verified'))
                    ->nullable(),
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
     * Build a data-URI initials avatar for a user with no uploaded photo.
     *
     * Generated inline rather than fetched from a third-party initials service:
     * the panel lists every user, so a remote URL would leak the whole user
     * table to that service on each page view.
     */
    public static function initialsAvatarUrl(User $user): string
    {
        // Initials derive from the user-supplied name, so they reach this SVG
        // as untrusted text: a name beginning with "<" yields initials that
        // would otherwise break out of the <text> element. Escaped rather than
        // stripped so legitimate names such as "A&B" still render.
        $initials = e(Str::of($user->initials())->trim()->upper()->value());

        // The gradient must come from the same avatarGradient() source as the
        // Flux sites: a user who is teal in the sidebar and orange in this
        // table is a bug, not a stylistic choice.
        $gradient = $user->avatarGradient();

        // Unique per user: several of these render on one table page, and a
        // collision would matter again if the SVG is ever inlined.
        $gradientId = 'grad-'.$user->getKey();

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                <defs>
                    <linearGradient id="{$gradientId}" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="{$gradient['from']}"/>
                        <stop offset="1" stop-color="{$gradient['to']}"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" fill="url(#{$gradientId})"/>
                <text x="50%" y="50%" fill="#ffffff"
                      font-family="system-ui, sans-serif" font-size="26" font-weight="500"
                      text-anchor="middle" dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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

        return $role->description();
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
