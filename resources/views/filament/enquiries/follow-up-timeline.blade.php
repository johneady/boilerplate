{{--
    The automatic email sequence for one enquiry: each email, its day, and
    whether it was sent, is scheduled, or will not go out (the enquiry was
    closed or the customer stopped the emails). From Enquiry::followUpTimeline().
--}}
@php
    $settings = app(\App\Settings\Settings::class);
    $subjects = [
        1 => __('voltiva.enquiries.emails.1'),
        2 => __('voltiva.enquiries.emails.2'),
        3 => __('voltiva.enquiries.emails.3'),
        4 => __('voltiva.enquiries.emails.4'),
        5 => __('voltiva.enquiries.emails.5'),
    ];
@endphp

<ol class="grid gap-3 md:grid-cols-5">
    @foreach ($getRecord()->followUpTimeline() as $email)
        <li @class([
            'rounded-lg border p-4 text-sm',
            'border-success-300 bg-success-50 dark:border-success-500/40 dark:bg-success-500/10' => $email['state'] === 'sent',
            'border-gray-200 dark:border-white/10' => $email['state'] === 'scheduled',
            'border-dashed border-gray-300 opacity-60 dark:border-white/20' => $email['state'] === 'stopped',
        ])>
            <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                {{ $email['day'] === 0 ? __('voltiva.enquiries.timeline.immediately') : __('voltiva.enquiries.timeline.day', ['day' => $email['day']]) }}
            </p>
            <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $subjects[$email['step']] ?? '' }}</p>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
                @switch ($email['state'])
                    @case ('sent')
                        {{ __('voltiva.enquiries.timeline.sent') }}
                        @break
                    @case ('scheduled')
                        {{ __('voltiva.enquiries.timeline.scheduled', ['date' => $settings->formatDate($email['date'])]) }}
                        @break
                    @default
                        {{ __('voltiva.enquiries.timeline.stopped') }}
                @endswitch
            </p>
        </li>
    @endforeach
</ol>
