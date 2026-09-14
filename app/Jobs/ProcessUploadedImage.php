<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

/**
 * Decode a user-supplied image and write the configured conversions.
 *
 * The source file is read from a private disk, re-encoded once per conversion,
 * and the originals are deleted. The original is never served: re-encoding is
 * what strips EXIF (GPS included) and any payload smuggled into a file that is
 * a structurally valid image, so serving the uploaded bytes would give away the
 * protection this job exists to provide.
 *
 * Conversions come from config/images.php rather than being hardcoded, so a
 * second consumer (a gallery, say) adds a key there instead of a job here.
 *
 * Every dispatch names an App\Models\Media row, written by
 * App\Media\MediaManager before the job is queued. That row -- not a cache
 * marker -- is what the cancel path acts on: a user who removes a file while
 * this job is still queued deletes the row, and a job that cannot find its row
 * discards the conversions it just wrote. The avatar and logo flows needed a
 * cache marker only because they had no row to consult until processing
 * finished.
 */
class ProcessUploadedImage extends Job
{
    /**
     * Create a new job instance.
     *
     * @param  string  $sourcePath  Path on the private disk holding the upload.
     * @param  string  $conversionSet  Key under config('images.conversions').
     * @param  string  $targetDirectory  Directory on the image disk to write into.
     * @param  int  $mediaId  The App\Models\Media row this job fills in.
     */
    public function __construct(
        public string $sourcePath,
        public string $conversionSet,
        public string $targetDirectory,
        public int $mediaId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $source = Storage::disk('local');

        if (! $source->exists($this->sourcePath)) {
            return;
        }

        /** @var array<string, array{width: int, height: int, fit: string}> $conversions */
        $conversions = config("images.conversions.{$this->conversionSet}", []);

        if ($conversions === []) {
            throw new RuntimeException("No conversions configured for [{$this->conversionSet}].");
        }

        /** @var string $driver */
        $driver = config('images.driver');

        $manager = ImageManager::usingDriver($driver);

        $contents = $source->get($this->sourcePath);

        // The local disk is configured with `throw => false`, so an unreadable
        // file yields null rather than an exception. Passing that straight to
        // decodeBinary() would raise a TypeError before the rollback below is
        // in scope, so it is turned into a normal job failure here.
        if ($contents === null) {
            throw new RuntimeException("Unable to read staged upload [{$this->sourcePath}].");
        }

        $target = Storage::disk($this->imageDisk());
        $written = [];

        try {
            foreach ($conversions as $name => $conversion) {
                // Decoded per conversion: modifiers mutate the image in place,
                // so a single instance would compound crops across sizes.
                $image = $manager->decodeBinary($contents);

                $written[$name] = $this->writeConversion($image, $name, $conversion, $target);
            }
        } catch (Throwable $exception) {
            // A partially written set would leave the model pointing at sizes
            // that do not all exist. Roll back before the base class retries.
            foreach ($written as $path) {
                $target->delete($path);
            }

            throw $exception;
        }

        // The staged original is removed before the previous avatar set is
        // pruned: once the source is gone a retry early-returns, so pruning
        // last means a retry can never destroy the old set after the new one
        // has already been rolled back.
        $source->delete($this->sourcePath);

        // Oriented before its geometry is recorded, because orient() swaps
        // the axes of a phone photo: the conversions are written oriented, so
        // an un-oriented probe would file width and height the wrong way
        // round against the bytes the row describes.
        $probe = $manager->decodeBinary($contents);
        $probe->orient();

        $this->attachToMedia($written, $probe);
    }

    /**
     * Clean up after a job that exhausted its attempts.
     *
     * The staged original and anything a killed attempt left half-written are
     * garbage by then: no retry is coming, nothing else reads them, and the
     * staging directory has no other collector. The media row is left alone
     * -- it is either collected by the orphan prune (an abandoned form) or
     * replaced by the user re-uploading (an avatar whose row shows the
     * placeholder until then).
     */
    public function failed(?Throwable $exception): void
    {
        parent::failed($exception);

        Storage::disk('local')->delete($this->sourcePath);

        Storage::disk($this->imageDisk())->deleteDirectory($this->targetDirectory);
    }

    /**
     * Record the written conversions on the media row that queued this job.
     *
     * The row already exists -- App\Media\MediaManager writes it before
     * dispatching -- so this fills in only what processing produced. Until it
     * runs, `conversions` is null and Media::url() returns null, which is what
     * makes an upload still in flight render as a placeholder rather than as a
     * link to files nothing has written.
     *
     * A row deleted while the job was queued is the cancel path: the user hit
     * Remove, and the conversions written moments ago must go with it rather
     * than being left on disk with nothing pointing at them. That replaces the
     * cache-marker guard the avatar and logo flows needed, because here there
     * IS a row to consult.
     *
     * @param  array<string, string>  $written
     */
    protected function attachToMedia(array $written, ImageInterface $probe): void
    {
        $media = Media::find($this->mediaId);

        if ($media === null) {
            Storage::disk($this->imageDisk())->deleteDirectory($this->targetDirectory);

            return;
        }

        $media->forceFill([
            'conversions' => $written,
            'width' => $probe->width(),
            'height' => $probe->height(),
        ])->save();
    }

    /**
     * Apply one conversion and write it to the image disk.
     *
     * @param  array{width: int, height: int, fit: string}  $conversion
     */
    protected function writeConversion(
        ImageInterface $image,
        string $name,
        array $conversion,
        Filesystem $target,
    ): string {
        // Phone cameras record rotation in EXIF rather than rotating the
        // pixels. Re-encoding discards that tag, so an unoriented image would
        // be written permanently sideways.
        $image->orient();

        if ($conversion['fit'] === 'cover') {
            $image->cover($conversion['width'], $conversion['height']);
        } else {
            $image->scaleDown($conversion['width'], $conversion['height']);
        }

        /** @var string $format */
        $format = config('images.format');

        /** @var int $quality */
        $quality = config('images.quality');

        $encoded = $image->encodeUsingFileExtension($format, quality: $quality);

        $path = $this->targetDirectory.'/'.$name.'.'.$format;

        $target->put($path, (string) $encoded);

        return $path;
    }

    /**
     * The disk processed images are written to.
     */
    protected function imageDisk(): string
    {
        /** @var string $disk */
        $disk = config('images.disk');

        return $disk;
    }
}
