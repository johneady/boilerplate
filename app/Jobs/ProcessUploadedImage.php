<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Decode a user-supplied image and write the configured conversions.
 *
 * The source file is read from a private disk, re-encoded once per conversion,
 * and the originals are deleted. The original is never served: re-encoding is
 * what strips EXIF (GPS included) and any payload smuggled into a file that is
 * a structurally valid image, so serving the uploaded bytes would give away
 * the protection this job exists to provide.
 *
 * Conversions come from config/images.php rather than being hardcoded, so a
 * second consumer (a gallery, say) adds a key there instead of a job here.
 */
class ProcessUploadedImage extends Job
{
    /**
     * Create a new job instance.
     *
     * @param  string  $sourcePath  Path on the private disk holding the upload.
     * @param  string  $conversionSet  Key under config('images.conversions').
     * @param  string  $targetDirectory  Directory on the image disk to write into.
     */
    public function __construct(
        public string $sourcePath,
        public string $conversionSet,
        public string $targetDirectory,
        public ?int $userId = null,
        public ?int $dispatchedAt = null,
    ) {
        $this->dispatchedAt ??= time();
    }

    /**
     * The cache key holding the moment a user last removed their avatar.
     */
    public static function removalKey(int $userId): string
    {
        return "avatar-removed-at:{$userId}";
    }

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
        } catch (\Throwable $exception) {
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

        $this->attachToUser($written);
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
     * Point the user at the freshly written conversions.
     *
     * @param  array<string, string>  $written
     */
    protected function attachToUser(array $written): void
    {
        if ($this->userId === null) {
            return;
        }

        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        // A user who hits Remove while this job is still queued means it: the
        // conversions are written but must not be attached, or the avatar the
        // user just deleted would reappear when the worker caught up.
        $removedAt = Cache::get(self::removalKey($this->userId));

        if ($removedAt !== null && $removedAt >= $this->dispatchedAt) {
            Storage::disk($this->imageDisk())->deleteDirectory($this->targetDirectory);

            return;
        }

        $previousDirectory = $user->avatar_path;

        $user->forceFill(['avatar_path' => $this->targetDirectory])->save();

        // Replacing an avatar leaves the previous set orphaned on disk.
        if ($previousDirectory !== null && $previousDirectory !== $this->targetDirectory) {
            Storage::disk($this->imageDisk())->deleteDirectory($previousDirectory);
        }
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
