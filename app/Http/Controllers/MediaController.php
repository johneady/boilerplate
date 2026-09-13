<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a private file that is never reachable as a static URL.
 *
 * Documents are stored as uploaded -- unlike images, nothing re-encodes them,
 * so the bytes are exactly what somebody supplied. That is why they live on the
 * private disk and come out through here: a file served from the public
 * directory is served by nginx with no PHP involved, so there is no point at
 * which anything can decide whether the requester should have it.
 *
 * Authorisation is TWO independent checks and both are load-bearing:
 *
 * - The signature (route middleware) proves the link was issued by this
 *   application and has not expired.
 * - The owning model's policy proves this particular user may see it, checked
 *   here at request time.
 *
 * The signature alone is not enough: a signed URL is a bearer token, so a link
 * pasted into a shared channel would hand the file to anyone holding it until
 * it expired. The policy alone is not enough either, because it is what lets
 * the link be shortened rather than permanent.
 */
class MediaController extends Controller
{
    /**
     * Stream one media file to an authorised requester.
     *
     * @throws AuthorizationException
     */
    public function show(Media $media): StreamedResponse
    {
        $this->authorizeAccess($media);

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            abort(404);
        }

        // Streamed rather than read into memory: an attachment may be large,
        // and a worker holding a whole file in memory per request is how a
        // download turns into an outage.
        return $disk->download($media->path, $media->file_name);
    }

    /**
     * Fail unless the requester may see the record this file belongs to.
     *
     * The file inherits its owner's authorisation rather than carrying rules of
     * its own. A separate media policy would be a second place to keep in step
     * with the first, and the question "may this person see this file" has no
     * answer that differs from "may they see the record it is attached to".
     *
     * An ownerless file -- one still awaiting attachment, or whose owner was
     * deleted -- has nothing to inherit from, so it is refused outright rather
     * than defaulting open.
     *
     * The one case inheritance cannot express is a file attached to the
     * requester's OWN account. UserPolicy::view is an admin-panel permission
     * ("may this person browse user accounts"), so inheriting it would deny
     * every ordinary user their own uploads. Owning the record is authorisation
     * enough, and it is checked before the policy rather than through it.
     *
     * @throws AuthorizationException
     */
    private function authorizeAccess(Media $media): void
    {
        // ownerOrNull() rather than the relation directly: a row naming a class
        // a later release removed throws Error on access, which would surface
        // as a 500 instead of the refusal this is deciding.
        $owner = $media->ownerOrNull();

        if ($owner === null) {
            throw new AuthorizationException;
        }

        $user = request()->user();

        if ($owner instanceof User && $user !== null && $owner->is($user)) {
            return;
        }

        Gate::authorize('view', $owner);
    }
}
