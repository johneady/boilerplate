<?php

/*
 * The validation messages the public forms can show, in this language.
 * Only these: any other key falls back to lang/en/validation.php.
 */

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],
    'exists' => 'El :attribute seleccionado no es válido.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo electrónico',
        'phone' => 'teléfono',
        'location' => 'ubicación',
        'message' => 'mensaje',
        'subject' => 'asunto',
        'vehicleId' => 'coche',
    ],
];
