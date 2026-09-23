{{--
    The Voltiva home page, in the order the brief sets out for a customer:
    what Voltiva sells (hero, the range), why it is different, which car suits
    them, what it costs, what happens after they buy, and how to get in touch.

    No :title or :image is passed on purpose: the home page takes the
    site-wide SEO title and social image from Settings rather than its own.

    $vehicles and $articles come from HomeController.
--}}
<x-layouts::public>
    {{-- Hero --}}
    {{--
        The headline sits on white above the photograph rather than over it,
        so any photo an editor chooses works: text laid over a picture only
        stays readable for pictures with empty space in the right place.
    --}}
    <section>
        <div class="mx-auto grid max-w-360 gap-8 px-4 pt-12 pb-10 sm:px-6 lg:grid-cols-[1fr_auto] lg:items-end lg:px-10 lg:pt-16 lg:pb-12">
            <div>
                <h1 class="max-w-4xl text-5xl font-medium tracking-tight text-balance sm:text-6xl lg:text-7xl">
                    {{ __('Electric Mobility Made Simple') }}
                </h1>
                <p class="mt-5 max-w-xl text-lg text-neutral-600 sm:text-xl">
                    {{ __('Electric cars for everyday life in Mallorca') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-voltiva.button :href="route('cars.index')">{{ __('View Electric Cars') }}</x-voltiva.button>
                <x-voltiva.button :href="route('finder')" variant="secondary">
                    {{ __('Find Your Car') }}</x-voltiva.button>
            </div>
        </div>

        <x-voltiva.image
            src="/storage/site/hero.webp"
            :alt="__('A compact electric car in a bright studio')"
            ratio="aspect-4/3 sm:aspect-16/9 lg:aspect-21/9"
            eager
            class="[&_img]:object-[20%_60%] sm:[&_img]:object-[50%_65%]"
        />
    </section>

    {{-- Electric cars --}}
    <section class="mx-auto w-full max-w-360 px-4 py-24 sm:px-6 lg:px-10 lg:py-32">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <x-voltiva.section-heading
                :eyebrow="__('Electric Cars')"
                :title="__('Small on the outside. Made for island life.')"
                :intro="__('Compact, quiet electric cars that are easy to park, cheap to run and charge from a normal socket at home.')"
            />
            <x-voltiva.button :href="route('compare')" variant="secondary" class="shrink-0">
                {{ __('Compare cars') }}</x-voltiva.button>
        </div>

        {{-- The two classes, explained before the cars themselves. --}}
        <div class="mt-12 grid gap-4 md:grid-cols-2">
            @foreach (\App\Voltiva\VehicleCategory::cases() as $category)
                <a
                    href="{{ route('cars.category', $category->value) }}"
                    class="group flex items-center justify-between gap-6 border border-neutral-200 p-6 transition hover:border-neutral-950"
                >
                    <div>
                        <p class="text-lg font-medium">
                            {{ __(':class Electric Cars', ['class' => $category->label()]) }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-600">{{ __($category->audience()) }}</p>
                    </div>
                    <flux:icon.arrow-right class="shrink-0 transition group-hover:translate-x-1" />
                </a>
            @endforeach
        </div>

        <div class="mt-12 grid gap-x-8 gap-y-14 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($vehicles as $vehicle)
                <x-voltiva.vehicle-card :vehicle="$vehicle" />
            @endforeach
        </div>
    </section>

    {{-- Why Voltiva --}}
    <div class="bg-neutral-50 py-24 lg:py-32">
        <x-voltiva.split-section
            image="/storage/site/why-voltiva.webp"
            :alt="__('A village in the Serra de Tramuntana, Mallorca')"
            :eyebrow="__('Why Voltiva')"
            :title="__('A local team, from first question to first service.')"
            :href="route('pages.show', 'about')"
            :link-label="__('About Voltiva')"
        >
            <p>
                {{ __('We are based in Mallorca and only sell here, so we know the roads, the hills and the parking. You deal with the same small team from your first question to your first service.') }}
            </p>
        </x-voltiva.split-section>

        <div class="mx-auto mt-20 grid max-w-360 gap-10 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-10">
            @foreach ([
                ['step' => '01', 'title' => __('Choose your car'), 'body' => __('Compare the range online, or answer four questions and we will suggest the right car.')],
                ['step' => '02', 'title' => __('Test drive it'), 'body' => __('We bring the car to you anywhere on the island, or visit us in Palma.')],
                ['step' => '03', 'title' => __('We register it'), 'body' => __('We handle the paperwork and plates. Your car arrives ready to drive.')],
                ['step' => '04', 'title' => __('Drive, and we look after it'), 'body' => __('Local servicing, a battery warranty and a team that answers the phone.')],
            ] as $step)
                <div class="border-t border-neutral-950 pt-5">
                    <p class="text-volt-700 text-sm">{{ $step['step'] }}</p>
                    <h3 class="mt-3 text-lg font-medium">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Batteries & range, and charging --}}
    <div class="space-y-24 py-24 lg:space-y-32 lg:py-32">
        <x-voltiva.split-section
            image="/storage/site/batteries.webp"
            :alt="__('A winding coast road at Cap de Formentor, Mallorca')"
            :eyebrow="__('Batteries & Range')"
            :title="__('More range between charges.')"
            :href="route('pages.show', 'batteries-and-range')"
            :link-label="__('How batteries and range work')"
            reverse
        >
            <p>{{ __('Enough for a week of town trips, or a day out to the coast and back, on a single charge.') }}</p>
            <p>{{ __('A long-life lithium battery that needs no maintenance, backed by a battery warranty.') }}</p>
            {{-- The benefit first, then the technical detail with each term explained. --}}
            <p class="text-base text-neutral-500">
                72V 150<x-voltiva.term key="ah" /> <x-voltiva.term key="lifepo4" /> · 10.8 <x-voltiva.term key="kwh" />
            </p>
        </x-voltiva.split-section>

        <x-voltiva.split-section
            image="/storage/site/charging.webp"
            :alt="__('An electric car charging at home')"
            :eyebrow="__('Charging')"
            :title="__('Charge at home, from a normal socket.')"
            :href="route('pages.show', 'charging')"
            :link-label="__('Charging explained')"
        >
            <p>
                {{ __('Plug in overnight and wake up to a full battery. No wallbox, no app, no charging station required – and a full charge costs about the same as a coffee.') }}
            </p>
        </x-voltiva.split-section>
    </div>

    {{-- Registration, servicing, finance --}}
    <section class="mx-auto w-full max-w-360 px-4 pb-24 sm:px-6 lg:px-10 lg:pb-32">
        <x-voltiva.section-heading :eyebrow="__('After you buy')" :title="__('Everything around the car, handled.')" />

        <div class="mt-12 grid gap-x-8 gap-y-14 md:grid-cols-3">
            @foreach ([
                ['image' => '/storage/site/registration.webp', 'slug' => 'registration', 'title' => __('Registration'), 'body' => __('We register your car with the DGT, supply the CoC and fit the plates.'), 'link' => __('How registration works')],
                ['image' => '/storage/site/servicing.webp', 'slug' => 'servicing-and-support', 'title' => __('Servicing'), 'body' => __('Simple, affordable servicing in Mallorca, with collection from your home.'), 'link' => __('Servicing & Support')],
                ['image' => '/storage/site/finance.webp', 'slug' => 'finance', 'title' => __('Finance'), 'body' => __('Spread the cost over 24 to 60 months, with a quote in minutes.'), 'link' => __('Finance options')],
            ] as $card)
                <a href="{{ route('pages.show', $card['slug']) }}" class="group block">
                    <x-voltiva.image :src="$card['image']" alt="" ratio="aspect-3/2" />
                    <h3 class="mt-5 text-xl font-medium tracking-tight">{{ $card['title'] }}</h3>
                    <p class="mt-2 text-neutral-600">{{ $card['body'] }}</p>
                    <span class="group-hover:text-volt-700 mt-4 inline-flex items-center gap-1 text-sm font-medium">
                        {{ $card['link'] }}
                        <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- News & Advice --}}
    @if ($articles->isNotEmpty())
        <section class="border-t border-neutral-200">
            <div class="mx-auto w-full max-w-360 px-4 py-24 sm:px-6 lg:px-10 lg:py-32">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <x-voltiva.section-heading
                        :eyebrow="__('News & Advice')"
                        :title="__('Straight answers about electric cars.')"
                    />
                    <x-voltiva.button :href="route('news.index')" variant="secondary" class="shrink-0">
                        {{ __('All articles') }}</x-voltiva.button>
                </div>

                <div class="mt-12 grid gap-x-8 gap-y-14 md:grid-cols-3">
                    @foreach ($articles as $article)
                        <x-voltiva.article-card :article="$article" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-voltiva.enquiry-section source="home" />
</x-layouts::public>
