<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait DocumentValidationRules
{
    /**
     * Get the validation rules used to validate an uploaded document.
     *
     * The image counterpart of this trait guards uploads that are re-encoded
     * before anything is served. Nothing re-encodes a document, so this list is
     * the ONLY thing standing between an upload and a file stored as supplied
     * -- which is why it is an explicit allow-list rather than a deny-list, and
     * why the file is served from the private disk through a signed route even
     * once it passes.
     *
     * `mimes` checks the extension guessed from the file's CONTENTS, not the
     * name the browser sent, so renaming a payload to .pdf does not get it in.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function documentRules(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('media.accepted_document_extensions');

        /** @var int $maxKilobytes */
        $maxKilobytes = config('media.max_document_kilobytes');

        return [
            'required',
            'file',
            'mimes:'.implode(',', $extensions),
            'max:'.$maxKilobytes,
        ];
    }
}
