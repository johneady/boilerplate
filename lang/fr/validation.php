<?php

/*
 * The validation messages the public forms can show, in this language.
 * Only these: any other key falls back to lang/en/validation.php.
 */

return [
    'required' => 'Le champ :attribute est obligatoire.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'exists' => 'La valeur sélectionnée pour :attribute n’est pas valide.',

    'attributes' => [
        'name' => 'nom',
        'email' => 'e-mail',
        'phone' => 'téléphone',
        'location' => 'lieu',
        'message' => 'message',
        'subject' => 'objet',
        'vehicleId' => 'voiture',
    ],
];
