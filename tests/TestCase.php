<?php

namespace Tests;

use App\Media\MediaCollection;
use App\Models\Media;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Give a user a processed avatar, writing its conversion files to the disk.
     *
     * The avatar is a media row rather than a column, so a test that wants one
     * has to create the row AND the files it points at -- Media::url() returns
     * null for a row whose conversions are absent, which is the in-flight state
     * rather than a stored avatar.
     *
     * @param  array<int, string>|null  $conversions  Which conversions exist on
     *                                                disk. Pass a subset to test
     *                                                the missing-conversion
     *                                                fallback.
     */
    protected function giveAvatar(User $user, ?array $conversions = null, string $directory = 'avatars/1/abc'): Media
    {
        /** @var string $format */
        $format = config('images.format');

        /** @var string $disk */
        $disk = config('images.disk');

        /** @var array<string, mixed> $configured */
        $configured = config('images.conversions.avatar', []);

        $names = $conversions ?? array_keys($configured);

        $written = [];

        foreach ($names as $name) {
            $path = $directory.'/'.$name.'.'.$format;

            $written[$name] = $path;

            Storage::disk($disk)->put($path, 'x');
        }

        $media = new Media;

        $media->forceFill([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'collection' => MediaCollection::Avatar->value,
            'conversion_set' => 'avatar',
            'disk' => $disk,
            'path' => $directory,
            'file_name' => 'portrait.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'conversions' => $written,
            'width' => 512,
            'height' => 512,
            'uploaded_by' => $user->getKey(),
        ])->save();

        $user->unsetRelation('media');

        return $media;
    }

    /**
     * Store a processed logo and write its conversion files to the fake disk.
     *
     * The logo is an OWNERLESS media row -- it belongs to the installation, not
     * to any record -- so it cannot be created through a model's relation, and
     * every test that wants one would otherwise restate that.
     *
     * The files are written as well as the row, because Settings::logoUrl()
     * goes through Media::url(), and a row whose conversion files do not exist
     * is the in-flight state rather than a stored logo.
     *
     * @return Media The stored logo row.
     */
    protected function storeLogo(string $directory = 'logo/abc'): Media
    {
        /** @var string $format */
        $format = config('images.format');

        /** @var string $disk */
        $disk = config('images.disk');

        $conversions = [];

        /** @var array<string, mixed> $configured */
        $configured = config('images.conversions.logo', []);

        foreach (array_keys($configured) as $name) {
            $path = $directory.'/'.$name.'.'.$format;

            $conversions[$name] = $path;

            Storage::disk($disk)->put($path, 'x');
        }

        $media = new Media;

        $media->forceFill([
            'collection' => MediaCollection::Logo->value,
            'conversion_set' => 'logo',
            'disk' => $disk,
            'path' => $directory,
            'file_name' => 'logo.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'conversions' => $conversions,
            'width' => 512,
            'height' => 512,
        ])->save();

        // Settings memoises the resolved logo for the life of the instance, so
        // a test that stores one after touching settings would otherwise keep
        // reading the "no logo" answer it cached.
        app()->forgetInstance(Settings::class);

        return $media;
    }
}
