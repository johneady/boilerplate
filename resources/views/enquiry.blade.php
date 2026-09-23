{{--
    Register Your Interest. Opened from a car ("?vehicle=<slug>") the form
    arrives with that car selected; any other entry point starts blank. The
    form validates both query values itself, so a stale or edited link is
    simply ignored.
--}}
<x-layouts::public
    :title="__('Register your interest')"
    :description="__('Tell us about the way you drive and a member of the Voltiva team in Mallorca will call you back within one working day.')"
>
    <section class="mx-auto grid w-full max-w-360 gap-14 px-4 pt-16 pb-24 sm:px-6 lg:grid-cols-[1fr_1.2fr] lg:gap-24 lg:px-10 lg:pt-24">
        <div>
            <x-voltiva.section-heading
                as="h1"
                :eyebrow="__('Register your interest')"
                :title="__('Let’s find the right car for you.')"
                :intro="__('Tell us a little about how you drive. A member of our team in Mallorca will call you within one working day – no obligation, no pressure.')"
            />

            <ol class="mt-12 space-y-6">
                @foreach ([
                    __('We call you to talk through your driving and answer your questions.'),
                    __('We arrange a test drive, at your home or with us in Palma.'),
                    __('You get a clear written quote, with finance if you want it.'),
                    __('We register the car and deliver it, ready to drive.'),
                ] as $index => $step)
                    <li class="flex gap-5">
                        <span class="text-volt-700 text-sm">0{{ $index + 1 }}</span>
                        <span class="text-neutral-700">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>

            <x-voltiva.image
                src="/storage/site/why-voltiva.webp"
                alt=""
                ratio="aspect-4/3"
                class="mt-12 hidden lg:block"
            />
        </div>

        <div class="border border-neutral-200 p-6 sm:p-10">
            {{-- Only string values are passed on: ?vehicle[]=x would otherwise be a type error. --}}
            <livewire:enquiry-form
                :vehicle="is_string(request()->query('vehicle')) ? request()->query('vehicle') : null"
                :source="is_string(request()->query('source')) ? request()->query('source') : 'register'"
            />
        </div>
    </section>
</x-layouts::public>
