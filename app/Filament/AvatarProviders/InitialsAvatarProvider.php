<?php

namespace App\Filament\AvatarProviders;

use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel's fallback avatar: the same gradient initials image the users
 * table shows, replacing Filament's default UiAvatars circle.
 *
 * Reached only when User::getFilamentAvatarUrl() returns null -- no photo has
 * been uploaded (or its conversions are still processing). The default
 * provider builds its initials through ui-avatars.com, which leaks the
 * signed-in user's name to a third party on every panel page view and paints
 * a flat near-black circle that matches nothing else in the application.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function __construct(private readonly UiAvatarsProvider $uiAvatars) {}

    public function get(Model $record): string
    {
        // This panel only ever authenticates App\Models\User, so the vendor
        // default stands in for any other model rather than failing the
        // render -- an avatar is decoration, not worth a 500.
        return $record instanceof User
            ? $record->initialsAvatarUrl()
            : $this->uiAvatars->get($record);
    }
}
