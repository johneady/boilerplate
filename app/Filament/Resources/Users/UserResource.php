<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // An admin editing themselves cannot drop their own rights:
                // doing so would lock them out of this panel on save, with no
                // way back in short of re-seeding or another admin's help.
                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->disabled(fn (?User $record): bool => static::isCurrentUser($record))
                    ->helperText(fn (?User $record): string => static::isCurrentUser($record)
                        ? 'You cannot remove your own administrator rights.'
                        : 'Administrators may sign in to this admin panel.'),
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
                    ->label('Avatar')
                    ->getStateUsing(fn (User $record): ?string => filled($url = $record->avatarUrl())
                        ? url($url)
                        : null)
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => static::initialsAvatarUrl($record)),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                IconColumn::make('is_admin')
                    ->label('Administrator')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Administrator'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
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

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                <rect width="64" height="64" fill="#dbeafe"/>
                <text x="50%" y="50%" fill="#1d4ed8"
                      font-family="system-ui, sans-serif" font-size="26" font-weight="500"
                      text-anchor="middle" dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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
     * is_admin is not mass-assignable (see the User model's Fillable
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
        // Enforced here as well as on the disabled toggle: a disabled field is
        // dropped from the payload, so without this an operator could still
        // demote themselves by posting is_admin directly.
        $user->is_admin = static::isCurrentUser($user)
            ? true
            : (bool) ($data['is_admin'] ?? false);

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
