<?php

namespace App\Media;

use App\Concerns\DocumentValidationRules;
use App\Concerns\ImageValidationRules;
use App\Jobs\ProcessUploadedImage;
use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The single path by which a file becomes a media row.
 *
 * Everything that stores a file goes through here -- the profile page, the
 * settings logo, a Filament resource -- so the decisions that make an upload
 * safe are made once rather than at each call site:
 *
 * - An image is STAGED on the private disk and handed to
 *   App\Jobs\ProcessUploadedImage, which re-encodes it and deletes the original.
 *   The uploaded bytes are never served. Re-encoding is a security control, not
 *   a resize: it discards EXIF (GPS on a phone photo) and anything smuggled
 *   into a structurally valid image. See .ai/rules/concerns.md.
 * - A DOCUMENT is stored as uploaded but stays on the private disk, reachable
 *   only through the signed route where a policy is consulted. Nothing served
 *   from the public origin is ever bytes a user supplied.
 *
 * The row is written BEFORE the job runs, deliberately. It carries the file's
 * identity -- who uploaded it, what it was called, which collection it belongs
 * to -- and the job fills in only what processing produces. A row that appeared
 * afterwards would mean an upload in flight is indistinguishable from one that
 * never happened, and there would be nothing for the cancel path to act on.
 */
class MediaManager
{
    use DocumentValidationRules, ImageValidationRules;

    /**
     * The validation rules an upload for this collection must satisfy.
     *
     * Exposed so a form validates with the SAME rules the manager would apply,
     * rather than a call site restating them and drifting. An image collection
     * gets the image allow-list (the formats the processing job can decode); a
     * document collection gets the document one.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public function rulesFor(MediaCollection $collection): array
    {
        return $collection->conversionSet() === null
            ? $this->documentRules()
            : $this->imageRules();
    }

    /**
     * Store an upload and attach it to a model.
     *
     * @param  (Model&HoldsMedia)|null  $owner  Null attaches nothing yet -- the row is an
     *                                          orphan the prune will collect unless a caller
     *                                          attaches it, which is how a create form holds
     *                                          a file before its record exists.
     */
    public function attach(
        UploadedFile $file,
        MediaCollection $collection,
        (Model&HoldsMedia)|null $owner = null,
        ?User $uploader = null,
    ): Media {
        // Validated here rather than trusted from the caller. A call site that
        // forgot to validate is the case this exists for, and an upload that
        // reached the disk before anyone checked it is already stored.
        Validator::make(
            ['file' => $file],
            ['file' => $this->rulesFor($collection)],
        )->validate();

        $uploader ??= Auth::user();

        // A single collection replaces rather than accumulates -- that is what
        // an avatar and a site logo both mean by "upload a new one". It applies
        // to an OWNERLESS single collection too (the logo belongs to the
        // installation, not to a record), which is why this does not require an
        // owner to take the replace path.
        if ($collection->isSingle()) {
            return $this->replaceSingle($file, $collection, $owner, $uploader);
        }

        return $this->store($file, $collection, $owner, $uploader);
    }

    /**
     * Attach an image already staged on the private disk.
     *
     * Filament's FileUpload stores the file itself and dehydrates to a PATH,
     * so there is no UploadedFile to hand attach(). The staged file is adopted
     * as-is rather than re-staged: it is already on the private disk, which is
     * where attach() would have put it.
     *
     * The caller SHOULD reject a bad path first so the user sees a friendly
     * message, but this re-checks with App\Media\StagedUpload regardless: a
     * guard that lives only at one call site is one refactor away from not
     * running, and dehydrated FileUpload state is client-controllable.
     */
    public function attachStagedImage(
        string $stagedPath,
        MediaCollection $collection,
        (Model&HoldsMedia)|null $owner = null,
        ?User $uploader = null,
    ): Media {
        if (! StagedUpload::isStagedPath($stagedPath)) {
            throw new RuntimeException('Refusing to adopt a file outside the staging directory.');
        }

        $source = Storage::disk('local');

        if (! $source->exists($stagedPath)) {
            throw new RuntimeException("Staged upload [{$stagedPath}] is missing.");
        }

        $uploader ??= Auth::user();

        $conversionSet = $collection->conversionSet();

        if ($conversionSet === null) {
            throw new RuntimeException('Only image collections may adopt a staged upload.');
        }

        // Validated here for the same reason attach() validates: the manager
        // is the one place that decides what may be stored, and a staged file
        // is not exempt -- the adopter hands it bytes that already sat on the
        // public disk, whose only earlier validation was the editor's own
        // upload-time check. A File rather than an UploadedFile because there
        // is no upload left by this point; the mimes and max rules read the
        // contents and size either way.
        Validator::make(
            ['file' => new File($source->path($stagedPath))],
            ['file' => $this->rulesFor($collection)],
        )->validate();

        $existing = $collection->isSingle()
            ? $this->existingQuery($collection, $owner)->get()->all()
            : [];

        $media = new Media;

        $media->forceFill([
            'model_type' => $owner?->getMorphClass(),
            'model_id' => $owner?->getKey(),
            'collection' => $collection->value,
            'conversion_set' => $conversionSet,
            'disk' => $collection->disk(),
            'path' => $collection->directory().'/'.Str::uuid()->toString(),
            // The staged name is a hash Filament generated, not anything a
            // person would recognise, so it is the best available label -- but
            // basename() still runs, because the path is client-supplied.
            'file_name' => basename($stagedPath),
            'mime_type' => $source->mimeType($stagedPath) ?: 'application/octet-stream',
            'size' => $source->size($stagedPath) ?: 0,
            'uploaded_by' => $uploader?->getKey(),
            'sort_order' => $this->nextSortOrder($collection, $owner),
        ]);

        $media->save();

        ProcessUploadedImage::dispatch(
            sourcePath: $stagedPath,
            conversionSet: $conversionSet,
            targetDirectory: $media->path,
            mediaId: $media->getKey(),
        );

        // Removed only after the replacement row exists, so a failure leaves
        // the installation with its previous logo rather than none.
        foreach ($existing as $previous) {
            $previous->delete();
        }

        return $media;
    }

    /**
     * Store an upload as a new row, leaving any existing files alone.
     */
    protected function store(
        UploadedFile $file,
        MediaCollection $collection,
        (Model&HoldsMedia)|null $owner,
        ?User $uploader,
    ): Media {
        // The uuid is the path, and the uploaded name is only ever data. A
        // path built from the name a browser sent is a directory traversal --
        // "../../.env" is a valid file name.
        $directory = $collection->directory().'/'.Str::uuid()->toString();

        $media = new Media;

        $media->forceFill([
            'model_type' => $owner?->getMorphClass(),
            'model_id' => $owner?->getKey(),
            'collection' => $collection->value,
            'conversion_set' => $collection->conversionSet(),
            'disk' => $collection->disk(),
            'path' => $directory,
            'file_name' => $this->sanitiseFileName($file),
            'mime_type' => $this->mimeType($file),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $uploader?->getKey(),
            'sort_order' => $this->nextSortOrder($collection, $owner),
        ]);

        $collection->conversionSet() === null
            ? $this->storeDocument($file, $media)
            : $this->stageForProcessing($file, $media);

        return $media;
    }

    /**
     * Store a non-image file as uploaded, on the private disk.
     *
     * `path` here is the FILE, not a directory, and `conversions` stays null --
     * that null is what the rest of the application reads as "this was not
     * re-encoded, do not serve it publicly".
     */
    protected function storeDocument(UploadedFile $file, Media $media): void
    {
        $stored = $file->storeAs(
            $media->path,
            Str::uuid()->toString(),
            ['disk' => $media->disk],
        );

        if ($stored === false) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        $media->forceFill(['path' => $stored])->save();
    }

    /**
     * Stage an image on the private disk and queue it for re-encoding.
     *
     * The row is saved with conversions still null, which is what marks it as
     * "in flight": url() returns null for it, so a caller renders a placeholder
     * rather than linking at files the worker has not written yet.
     */
    protected function stageForProcessing(UploadedFile $file, Media $media): void
    {
        // storeAs() on the private disk, never the public one: the unprocessed
        // original must not be reachable over HTTP even for the seconds before
        // the worker deletes it.
        $sourcePath = $file->storeAs(
            'uploads/pending',
            Str::uuid()->toString(),
            ['disk' => 'local'],
        );

        if ($sourcePath === false) {
            throw new RuntimeException('Unable to stage the uploaded image.');
        }

        $media->save();

        ProcessUploadedImage::dispatch(
            sourcePath: (string) $sourcePath,
            conversionSet: (string) $media->conversion_set,
            targetDirectory: $media->path,
            mediaId: $media->getKey(),
        );
    }

    /**
     * Replace the file in a single-file collection.
     *
     * The new row is written before the old files are removed, so a failure
     * partway leaves the record with its previous file rather than none.
     */
    protected function replaceSingle(
        UploadedFile $file,
        MediaCollection $collection,
        (Model&HoldsMedia)|null $owner,
        ?User $uploader,
    ): Media {
        /** @var array<int, Media> $existing */
        $existing = $this->existingQuery($collection, $owner)->get()->all();

        $media = $this->store($file, $collection, $owner, $uploader);

        foreach ($existing as $previous) {
            $previous->delete();
        }

        $owner?->unsetRelation('media');

        return $media;
    }

    /**
     * The rows already in a collection, owned or ownerless.
     *
     * An ownerless collection must be matched on a NULL owner explicitly.
     * Querying the collection alone would sweep in every record's files in a
     * same-named collection, and replacing the site logo would delete them.
     *
     * @return Builder<Media>
     */
    protected function existingQuery(MediaCollection $collection, (Model&HoldsMedia)|null $owner): Builder
    {
        $query = Media::query()->inCollection($collection);

        if ($owner === null) {
            return $query->whereNull('model_id')->whereNull('model_type');
        }

        return $query
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey());
    }

    /**
     * The position a newly attached file takes in its collection.
     */
    protected function nextSortOrder(MediaCollection $collection, (Model&HoldsMedia)|null $owner): int
    {
        if ($owner === null) {
            return 0;
        }

        $highest = $this->existingQuery($collection, $owner)->max('sort_order');

        // An empty collection starts at 0, not 1: max() of no rows is null, and
        // (int) null + 1 would number the first item as though something came
        // before it.
        return $highest === null ? 0 : (int) $highest + 1;
    }

    /**
     * The uploaded name, reduced to something safe to display and download.
     *
     * Kept for display and for a download's Content-Disposition, never used to
     * build a path. Basename strips any directory the browser sent, and the
     * length cap keeps it inside the column.
     */
    protected function sanitiseFileName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        $name = preg_replace('/[^\w.\- ]+/u', '', $name) ?? '';

        $name = trim($name);

        return $name === '' ? 'file' : Str::limit($name, 250, '');
    }

    /**
     * The file's type as determined from its CONTENTS.
     *
     * getMimeType() guesses from the bytes; getClientMimeType() returns what
     * the browser claimed, which an attacker sets freely.
     */
    protected function mimeType(UploadedFile $file): string
    {
        return $file->getMimeType() ?? 'application/octet-stream';
    }
}
