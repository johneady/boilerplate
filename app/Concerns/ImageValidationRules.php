<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ImageValidationRules
{
    /**
     * Get the validation rules used to validate an uploaded image.
     *
     * `mimes` is used rather than Laravel's `image` rule because it allows an
     * explicit list: the accepted formats are exactly the ones the processing
     * job can decode, so an upload that would fail on the worker is rejected
     * in the request instead. It checks the extension guessed from the file's
     * CONTENTS, not the name the browser sent.
     *
     * This also keeps SVG out, which matters because an SVG is a scriptable
     * document served from this application's own origin. Laravel's `image`
     * rule happens to reject SVG by default in this version, but it accepts
     * one again under `image:allow_svg` -- an explicit allow-list does not
     * depend on that default staying put.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function imageRules(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('images.accepted_extensions');

        /** @var int $maxKilobytes */
        $maxKilobytes = config('images.max_kilobytes');

        return [
            'required',
            'file',
            'mimes:'.implode(',', $extensions),
            'max:'.$maxKilobytes,
        ];
    }
}
