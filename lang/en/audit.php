<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Audit Log
|--------------------------------------------------------------------------
|
| Strings for the Filament audit log resource. Dotted keys rather than the JSON
| file's English-as-key convention -- see .ai/rules/i18n.md for which applies
| where.
|
| App\Audit\AuditEvent's own label() returns plain English and is NOT read from
| here: it is covered by a unit test, which has no container, so __() there
| fails outright. Event labels are translated at the point of display if they
| ever need to be.
|
*/

return [

    'resource' => [
        'label' => 'Audit entry',
        'plural_label' => 'Audit log',
    ],

    'fields' => [
        'event' => 'Event',
        'recorded' => 'Recorded',
        'actor' => 'Performed by',
        'actor_email' => 'Email address',
        'subject' => 'Record',
        'ip_address' => 'IP address',
        'user_agent' => 'Browser',
        'old_values' => 'Before',
        'new_values' => 'After',
        'attribute' => 'Attribute',
        'value' => 'Value',
        'context' => 'Details',
    ],

    'filters' => [
        'from' => 'Recorded from',
        'until' => 'Recorded until',
    ],

];
