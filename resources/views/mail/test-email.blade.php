<x-mail::message>
# {{ __('Mail delivery is working') }}

{{ __('This is a test email sent from the settings page. If you are reading it, :business can deliver email with the settings you just saved.', ['business' => $businessName]) }}

<x-mail::panel>
{{ __('Delivered via :mailer at :now.', ['mailer' => $mailer, 'now' => $sentAt]) }}
</x-mail::panel>

{{ __('Nothing else is needed -- this message exists only to prove the connection works.') }}
</x-mail::message>
