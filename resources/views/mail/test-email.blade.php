<x-mail::message>
# {{ __('Mail delivery is working') }}

{{ __('This is a test email sent from the settings page.') }}

{{ __('If you are reading it, mail delivery from :business is working.', ['business' => $businessName]) }}

{{ __('Sent: :now', ['now' => now()->toDateTimeString()]) }}
</x-mail::message>
