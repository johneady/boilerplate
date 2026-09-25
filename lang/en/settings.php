<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Settings
|--------------------------------------------------------------------------
|
| Strings for App\Filament\Pages\ManageSettings. Dotted keys rather than the
| JSON file's English-as-key convention -- see .ai/rules/i18n.md.
|
| Notifications keep their title and body adjacent so the pair is reworded
| together; a title that no longer matches its body is the usual way these
| drift.
|
*/

return [

    'actions' => [
        'save' => 'Save changes',
        'saved' => 'Settings saved',
    ],

    'mailer' => [
        'heading' => 'Change mailer',
        'button' => 'Mailer: :mailer',
        'tooltip' => 'Messages are sent through :mailer rather than SMTP. Change it with the mailer button on this tab.',
        'needs_from_address' => [
            'title' => 'Add a from address before changing the mailer',
            'body' => 'SMTP cannot be chosen until a from address is set, and this button is where the mailer is chosen. Set one on the Email tab, save, then come back.',
        ],
        'saved_to_log' => [
            'title' => 'Mailer saved, sending through the log',
            'body' => 'SMTP was chosen without a host, so messages are written to the application log until one is set.',
        ],
        'updated' => 'Mailer updated',
        'host_placeholder' => 'smtp.example.com',
        'port_placeholder' => '587',
        'remove_password' => 'Remove the stored password',
        'remove_password_help' => 'For a mail server that needs no password. A password typed into the field is used instead.',
    ],

    'logo' => [
        'heading' => 'Logo',
        'remove' => 'Remove logo',
        'remove_description' => 'The bundled mark is shown across the site again until another logo is uploaded.',
        'removed' => 'Logo removed',
        'rejected' => [
            'title' => 'Upload rejected',
            'body' => 'Choose a logo file to upload.',
        ],
        'uploaded' => [
            'title' => 'Logo uploaded',
            'body' => 'It will appear across the site, in the browser tab and in link previews once processed.',
        ],
    ],

    'test_email' => [
        'label' => 'Send test email',
        'description' => 'Sends a short message through the saved mail settings, so delivery can be confirmed end to end.',
        'recipient' => 'Deliver to',
        'sent' => 'Test email sent',
        'failed' => 'Test email failed',
        'logged' => [
            'title' => 'Test email written to the log',
            'body' => 'The mailer is set to log, so the message was written to the application log rather than delivered.',
        ],
    ],

];
