@props([
    'vehicle' => null,
    'source' => 'home',
    'title' => null,
])

{{--
    The strong enquiry section the brief asks to finish with: a full-width
    dark band over a Mallorca photograph, with the enquiry form on a white
    card. Used at the foot of the home page and every car page, passing the
    car (preselected) and the source (recorded on the enquiry).
--}}
<section
    id="enquire"
    {{ $attributes->class('relative isolate overflow-hidden bg-neutral-950 text-white scroll-mt-16') }}
>
    <img
        src="/storage/site/tramuntana.webp"
        alt=""
        loading="lazy"
        decoding="async"
        class="absolute inset-0 -z-10 size-full object-cover opacity-40"
    />
    <div class="absolute inset-0 -z-10 bg-linear-to-r from-neutral-950 via-neutral-950/80 to-neutral-950/30"></div>

    <div class="mx-auto grid max-w-360 gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[1fr_1.1fr] lg:gap-20 lg:px-10 lg:py-28">
        <div class="max-w-lg">
            <p class="text-volt-500 text-xs font-medium tracking-[0.2em] uppercase">
                {{ __('Register your interest') }}
            </p>
            <h2 class="mt-3 text-4xl font-medium tracking-tight text-balance sm:text-5xl">
                {{ $title ?? __('Ready to go electric?') }}
            </h2>
            <p class="mt-6 text-lg leading-relaxed text-neutral-300">
                {{ __('Tell us a little about how you drive. A member of our team in Mallorca will call you within one working day to answer your questions, arrange a test drive and prepare a quote.') }}
            </p>
            <ul class="mt-8 space-y-3 text-neutral-200">
                @foreach ([__('No obligation, no pressure'), __('Test drives anywhere on the island'), __('Registration and finance handled for you')] as $point)
                    <li class="flex items-center gap-3">
                        <flux:icon.check variant="micro" class="text-volt-500" /> {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white p-6 text-neutral-950 sm:p-10">
            <livewire:enquiry-form :vehicle="$vehicle?->slug" :source="$source" />
        </div>
    </div>
</section>
