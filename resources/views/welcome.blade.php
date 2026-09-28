{{--
    The lab's home page: what we do (prints from your phone), what it costs
    (the deal), and the two ways in -- scan the counter's code, or send from
    anywhere. The HTML shell, header nav and footer live in
    layouts/public.blade.php, shared with the content pages and the contact
    form.

    No :title is passed on purpose: the home page takes the site-wide SEO title
    rather than prefixing it with a page name.
--}}
<x-layouts::public>
    @php
        $counter = App\Models\PrintLocation::query()->active()->orderBy('name')->first();
        $quote = App\Prints\PrintPricing::quote(App\Prints\PrintPricing::BUNDLE_SIZE);
    @endphp

    <main class="flex flex-1 flex-col justify-center py-16">
        <div class="max-w-3xl">
            <flux:badge
                size="sm"
                color="teal"
                inset="top bottom"
            >{{ __('Rockport harborfront · Since 2011') }}</flux:badge>

            <h1 class="mt-6 text-5xl font-semibold tracking-tight text-balance sm:text-6xl">
                {{ __('Prints from your phone, ready in minutes.') }}
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{
                    __('Scan the code on our counter, or send from wherever you are: :business prints phone photos on real photo paper — glossy 4×6s, the way they belong on a fridge.',
                        ['business' => $businessName])
                }}
            </p>

            <div class="mt-10 flex flex-wrap items-center gap-3">
                <flux:button :href="route('photo.start')" variant="primary" icon="paper-airplane">
                    {{ __('Send us your photos') }}
                </flux:button>
                <flux:button href="#how" variant="ghost"> {{ __('How it works') }} </flux:button>
            </div>

            <p class="mt-6 text-sm text-neutral-500 dark:text-neutral-400">
                {{
                    __('One print is :unit. Any three prints for :bundle — that part is a deal.',
                        ['unit' => $quote->unit(), 'bundle' => $quote->bundle()])
                }}
            </p>
        </div>

        <div id="how" class="mt-24 grid gap-8 sm:grid-cols-3">
            @foreach ([
                ['heading' => __('Scan or tap'), 'body' => __('Point your camera at the code on our counter, or tap "Send us your photos". No app to install, no account to make — your browser does the work.')],
                ['heading' => __('Choose your prints'), 'body' => __('Pick your photos, say how many of each you want, and the 3-print deal applies itself. You will see the price before anything is sent.')],
                ['heading' => __('Pick up, or post'), 'body' => __('In the store: collect at the counter and pay there, nothing upfront. Anywhere else: pay on your phone and we post them the same day.')],
            ] as $step)
                <div class="rounded-xl border border-neutral-200 bg-white/60 p-6 dark:border-neutral-800 dark:bg-neutral-900/40">
                    <flux:heading>{{ $step['heading'] }}</flux:heading>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                        {{ $step['body'] }}
                    </p>
                </div>
            @endforeach
        </div>

        @if ($counter !== null)
            <div class="mt-20 flex flex-col items-center gap-8 rounded-2xl border border-teal-200 bg-teal-50/50 p-8 sm:flex-row sm:items-center dark:border-teal-800 dark:bg-teal-950/30">
                <div class="shrink-0 rounded-2xl bg-white p-4 shadow-sm dark:bg-neutral-900">
                    {!! $counter->qrCodeSvg() !!}
                </div>

                <div>
                    <flux:heading>{{ __('In the store') }}</flux:heading>
                    <p class="mt-2 max-w-lg text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                        {{ __('This is the code on our :name counter. Scan it, choose your photos, and put your phone away — we print while you browse, you pay when you collect.', ['name' => $counter->name]) }}
                    </p>
                    <p class="mt-3 text-sm font-medium text-teal-800 dark:text-teal-300">
                        {{ __(':address — no payment until pickup.', ['address' => $counter->address]) }}
                    </p>
                </div>
            </div>
        @endif
    </main>
</x-layouts::public>
