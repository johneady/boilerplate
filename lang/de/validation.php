<?php

/*
 * The validation messages the public forms can show, in this language.
 * Only these: any other key falls back to lang/en/validation.php.
 */

return [
    'required' => 'Das Feld :attribute ist erforderlich.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'max' => [
        'string' => 'Das Feld :attribute darf höchstens :max Zeichen haben.',
    ],
    'exists' => 'Die gewählte Angabe für :attribute ist ungültig.',

    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'location' => 'Ort',
        'message' => 'Nachricht',
        'subject' => 'Betreff',
        'vehicleId' => 'Auto',
    ],
];
