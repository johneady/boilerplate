<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ContactValidationRules
{
    /**
     * Get the validation rules used to validate a contact form submission.
     *
     * The lengths match the columns rather than being generously round: the
     * message column is text and could hold far more, but a contact form is
     * not a file upload, and an unbounded field is an easy way to fill a table.
     *
     * The email is checked for shape only, with no DNS lookup: the form is
     * public and unauthenticated, so a `dns` rule would let anybody make the
     * server perform lookups on demand, and a typo'd address costs nothing here
     * -- the message is stored either way.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function contactRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
