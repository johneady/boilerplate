<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the media library.
 *
 * Only viewing and deleting are granted. `create` and `update` are deliberately
 * absent, which denies them to everyone through BasePolicy's default:
 *
 * - A file is CREATED by uploading one, which goes through
 *   App\Media\MediaManager so the bytes are validated, staged privately and
 *   re-encoded. A create form on this resource would be a second way in that
 *   skipped all of it, writing a row that claims bytes nobody checked.
 * - A row is a statement about a file on disk -- which disk, which path, which
 *   size. Editing one cannot move the bytes, so an update would only ever make
 *   the row disagree with the file it describes.
 *
 * Deleting IS offered, because it is the one operation that keeps the row and
 * the disk in step: Media::delete() removes the files before the row.
 *
 * Note this guards the LIBRARY, not access to an individual file's contents.
 * Reading the bytes of a private document goes through
 * App\Http\Controllers\MediaController, which authorizes against the record the
 * file is attached to -- a user reaching their own attachment must not need a
 * panel permission.
 */
class MediaPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewMedia,
            'view' => Permission::ViewMedia,
            'delete' => Permission::DeleteMedia,
        ];
    }
}
