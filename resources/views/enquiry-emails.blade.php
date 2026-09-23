{{--
    Reached from the "stop the follow-up emails" link in every sequence email
    (signed). The GET shows the button; the POST, back to the same signed URL,
    ends the sequence. See EnquiryEmailsController for why it takes two steps.
--}}
<x-layouts::public :title="__('Follow-up emails')">
    <section class="mx-auto w-full max-w-2xl px-4 py-24 sm:px-6">
        @if ($stopped)
            <h1 class="text-4xl font-medium tracking-tight">{{ __('Done – no more follow-up emails.') }}</h1>
            <p class="mt-5 text-lg text-neutral-600">
                {{ __('We will not send any more automatic emails about your enquiry. If you have a question, you can still reply to any of our emails or call us.') }}
            </p>
            <x-voltiva.button :href="route('home')" variant="secondary" class="mt-10">
                {{ __('Back to the website') }}</x-voltiva.button>
        @else
            <h1 class="text-4xl font-medium tracking-tight">{{ __('Stop the follow-up emails?') }}</h1>
            <p class="mt-5 text-lg text-neutral-600">
                {{ __('We will stop sending automatic emails about your enquiry. Our team can still reply to you directly.') }}
            </p>
            <form method="POST" action="{{ $action }}" class="mt-10">
                @csrf
                <x-voltiva.button type="submit">{{ __('Stop the emails') }}</x-voltiva.button>
            </form>
        @endif
    </section>
</x-layouts::public>
