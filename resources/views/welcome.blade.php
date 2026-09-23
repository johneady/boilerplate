{{--
    The landing page: two open-access calculators, one for people thinking
    about becoming an agent and one for agents already in the business. No
    sign-up and no lead capture, so the cards go straight to the tools.

    No :title is passed on purpose: the home page takes the site-wide SEO title
    rather than prefixing it with a page name.
--}}
<x-layouts::public>
    <main class="flex flex-1 flex-col py-8 sm:py-16">
        <div class="max-w-3xl">
            <flux:badge size="sm" color="teal" inset="top bottom" icon="lock-closed">
                {{ __('Free · No sign-up · Nothing stored') }}
            </flux:badge>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                {{ __('Run the numbers on your real estate career.') }}
            </h1>

            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __('Two quick calculators from :business. Pick the one that fits where you are today. No account, no email address, no follow-up call.', ['business' => $businessName]) }}
            </p>
        </div>

        <div class="mt-10 grid gap-4 sm:mt-14 sm:grid-cols-2 sm:gap-6">
            @foreach ([
                [
                    'route' => 'calculators.income-planner',
                    'icon' => 'light-bulb',
                    'eyebrow' => __('Thinking about real estate?'),
                    'heading' => __('Income planner'),
                    'body' => __('Tell us the income you want. We will show the listings, buyer clients and weekly appointments it takes to get there.'),
                    'action' => __('Plan my income'),
                ],
                [
                    'route' => 'calculators.split-comparison',
                    'icon' => 'scale',
                    'eyebrow' => __('Already licensed?'),
                    'heading' => __('Split comparison'),
                    'body' => __('Enter your sales volume, commission, split and royalties. See what you take home now beside what you would keep with :business.', ['business' => $businessName]),
                    'action' => __('Compare my split'),
                ],
            ] as $calculator)
                <a
                    href="{{ route($calculator['route']) }}"
                    class="group relative flex flex-col rounded-2xl border border-neutral-200 bg-white/70 p-6 shadow-sm transition hover:border-teal-600/60 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 sm:p-8 dark:border-neutral-800 dark:bg-neutral-900/50 dark:hover:border-teal-400/60"
                >
                    <span class="flex size-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-300">
                        <flux:icon :name="$calculator['icon']" class="size-6" />
                    </span>

                    <span class="mt-5 text-sm font-medium text-teal-700 dark:text-teal-300">{{ $calculator['eyebrow'] }}</span>
                    <span class="mt-1 text-2xl font-semibold tracking-tight">{{ $calculator['heading'] }}</span>
                    <span class="mt-3 leading-relaxed text-neutral-600 dark:text-neutral-400">{{ $calculator['body'] }}</span>

                    <span class="mt-6 inline-flex items-center gap-1.5 font-medium text-teal-700 dark:text-teal-300">
                        {{ $calculator['action'] }}
                        <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-14 grid gap-6 sm:mt-20 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'clock', 'heading' => __('About a minute'), 'body' => __('One number for the planner, four for the comparison. Results update as you type.')],
                ['icon' => 'device-phone-mobile', 'heading' => __('Private by design'), 'body' => __('Everything is worked out on your own device. Nothing you enter is sent or saved.')],
                ['icon' => 'eye', 'heading' => __('Every assumption shown'), 'body' => __('The rates behind each result are listed under it, so you can see exactly how we got there.')],
            ] as $point)
                <div class="flex gap-3">
                    <flux:icon :name="$point['icon']" class="mt-0.5 size-5 shrink-0 text-teal-700 dark:text-teal-300" />
                    <div>
                        <p class="font-medium">{{ $point['heading'] }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                            {{ $point['body'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
</x-layouts::public>
