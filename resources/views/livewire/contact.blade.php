@php($page = $this->page)

{{--
    The public contact form.

    The layout is supplied by the component's render() rather than wrapped here:
    Livewire always wraps a full-page component in a layout, so naming it there
    is what both replaces the signed-in default and passes the page row's title
    through to the <head>.

    The heading and intro copy come from the 'contact' page row when one exists,
    so an administrator writes them in the panel alongside the other public
    pages. Both fall back to translated copy, because the form has to work on an
    instance whose pages were never seeded.
--}}
<div class="flex-1 px-4 py-16 sm:px-6 lg:py-24">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-4xl font-medium tracking-tight text-balance sm:text-5xl">
            {{ $page?->title ?? __('Contact') }}
        </h1>

        @if ($page !== null && filled($page->body))
            <div class="[&_a]:font-medium [&_a]:text-volt-700 [&_a]:underline [&_a]:underline-offset-2 [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_strong]:font-semibold [&_strong]:text-neutral-900 dark:[&_strong]:text-neutral-100 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 mt-6 text-base leading-relaxed text-neutral-600 dark:text-neutral-400">
                {!! $page->renderedBody() !!}
            </div>
        @else
            <p class="mt-6 text-base leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __('Send us a message and we will get back to you.') }}
            </p>
        @endif

        @if ($sent)
            <div
                class="mt-8 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200"
                data-test="contact-sent"
            >
                {{ __('Thank you. Your message has been sent.') }}
            </div>
        @endif

        <form wire:submit="submit" class="mt-8 space-y-6">
            <flux:input wire:model="name" :label="__('Your name')" type="text" required autocomplete="name" />

            <flux:input
                wire:model="email"
                :label="__('Your email address')"
                type="email"
                required
                autocomplete="email"
                :description="__('So we can reply to you.')"
            />

            <flux:input wire:model="subject" :label="__('Subject')" type="text" :description="__('Optional.')" />

            <flux:textarea wire:model="message" :label="__('Message')" rows="8" required />

            {{--
                The honeypot. Hidden from people with the hidden attribute and
                kept out of the tab order and the accessibility tree, so no
                keyboard or screen-reader user can land in it by accident --
                which is the difference between a spam trap and a trap for
                the people least able to work around it.

                autocomplete="off" matters as much: a browser filling a field
                called "website" from a saved profile would silently discard
                a genuine message.
            --}}
            <div hidden aria-hidden="true">
                <label for="contact-website">{{ __('Leave this field empty') }}</label>
                <input id="contact-website" type="text" wire:model="website" tabindex="-1" autocomplete="off" />
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary"> {{ __('Send message') }} </flux:button>

                <div wire:loading wire:target="submit">
                    <flux:text size="sm">{{ __('Sending...') }}</flux:text>
                </div>
            </div>
        </form>
    </div>
</div>
