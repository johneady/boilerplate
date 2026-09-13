<?php

namespace Database\Factories;

use App\Media\MediaCollection;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * Defaults to a PROCESSED avatar -- conversions written, geometry known --
     * because that is the state most tests want to assert against. The
     * in-flight state (conversions still null) is the `pending` state below,
     * named so a test asking for it says so.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $directory = 'avatars/'.Str::uuid()->toString();

        /** @var string $format */
        $format = config('images.format');

        return [
            'collection' => MediaCollection::Avatar->value,
            'conversion_set' => 'avatar',
            'disk' => config('images.disk'),
            'path' => $directory,
            'file_name' => 'portrait.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(20_000, 400_000),
            'conversions' => [
                'thumb' => $directory.'/thumb.'.$format,
                'full' => $directory.'/full.'.$format,
            ],
            'width' => 512,
            'height' => 512,
            'uploaded_by' => User::factory(),
            'sort_order' => 0,
        ];
    }

    /**
     * An image whose processing job has not run yet.
     *
     * Null conversions is what the whole pipeline reads as "in flight", so a
     * test for the placeholder path needs this rather than a deleted file.
     */
    public function pending(): self
    {
        return $this->state(fn (): array => [
            'conversions' => null,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * A non-image stored as uploaded on the private disk.
     */
    public function document(): self
    {
        return $this->state(fn (): array => [
            'collection' => MediaCollection::Attachment->value,
            'conversion_set' => null,
            'disk' => 'local',
            'path' => 'attachments/'.Str::uuid()->toString().'/'.Str::uuid()->toString(),
            'file_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'conversions' => null,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * The installation's logo, which belongs to no record.
     */
    public function logo(): self
    {
        $directory = 'logo/'.Str::uuid()->toString();

        /** @var string $format */
        $format = config('images.format');

        return $this->state(fn (): array => [
            'collection' => MediaCollection::Logo->value,
            'conversion_set' => 'logo',
            'path' => $directory,
            'model_type' => null,
            'model_id' => null,
            'conversions' => [
                'mark' => $directory.'/mark.'.$format,
                'favicon' => $directory.'/favicon.'.$format,
                'apple-touch' => $directory.'/apple-touch.'.$format,
                'social' => $directory.'/social.'.$format,
            ],
        ]);
    }
}
