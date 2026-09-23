{{--
    THE product template. Every car renders through this one view from the
    columns on its row -- adding a car is filling in a form in the admin
    panel, never a new design (brief, section 7).

    Customer first (section 8): each figure leads with what it means for the
    driver, and the technical detail follows with every term clickable for a
    plain explanation.

    Sections: hero · key benefits · at a glance · photos/video · battery &
    charging · interior & safety · specifications · registration, servicing,
    finance, accessories · FAQ · related advice · other cars · enquiry (with
    this car preselected).
--}}
@php
    $category = $vehicle->category;
    $gallery = $vehicle->galleryUrls();
    $video = $vehicle->videoEmbedUrl();
    // An illustrative household tariff for the "cost of a full charge" line.
    $fullChargeCost = \App\Voltiva\Money::format((int) round((float) $vehicle->battery_kwh * 20));
    $finance = config('voltiva.finance');

    $sections = array_filter([
        'overview' => __('Overview'),
        'battery' => __('Battery & charging'),
        'comfort' => filled($vehicle->comfort) || filled($vehicle->safety) ? __('Comfort & safety') : null,
        'specifications' => __('Specifications'),
        'ownership' => __('Ownership'),
        'faq' => filled($vehicle->faqs) ? __('FAQ') : null,
    ]);
@endphp

<x-layouts::public
    :title="$vehicle->name"
    :description="$vehicle->seo_description ?: $vehicle->summary"
    :image="$vehicle->imageUrl()"
    :schema="$vehicle->structuredData()"
>
    @unless ($vehicle->is_published)
        <div class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
            {{ __('This car is not published yet. Only people who can edit it see this page.') }}
        </div>
    @endunless

    {{-- Hero --}}
    <section class="mx-auto w-full max-w-360 px-4 pt-10 sm:px-6 lg:px-10 lg:pt-14">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-neutral-500">
            <a href="{{ route('cars.index') }}" class="hover:text-neutral-950">{{ __('Cars') }}</a>
            <span class="mx-1.5">/</span>
            <a
                href="{{ route('cars.category', $category->value) }}"
                class="hover:text-neutral-950"
            >{{ __(':class Electric Cars', ['class' => $category->label()]) }}</a>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <h1 class="text-5xl font-medium tracking-tight sm:text-6xl">{{ $vehicle->name }}</h1>
                <p class="mt-4 max-w-2xl text-xl text-neutral-700 sm:text-2xl">{{ $vehicle->tagline }}</p>
            </div>

            <div class="lg:text-right">
                <p class="text-sm text-neutral-500">{{ __('From') }}</p>
                <p class="text-3xl font-medium tracking-tight">{{ $vehicle->formattedPrice() }}</p>
                @if ($vehicle->formattedMonthlyFrom())
                    <p class="mt-1 text-sm text-neutral-600">
                        {{ __('or from :amount a month', ['amount' => $vehicle->formattedMonthlyFrom()]) }}
                    </p>
                @endif
                <div class="mt-5 flex flex-wrap gap-3 lg:justify-end">
                    <x-voltiva.button href="#enquire">{{ __('Register your interest') }}</x-voltiva.button>
                    <x-voltiva.button :href="route('compare', ['cars' => [$vehicle->slug]])" variant="secondary">
                        {{ __('Compare') }}</x-voltiva.button>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto mt-10 w-full max-w-360 sm:px-6 lg:px-10">
        <x-voltiva.image
            :src="$vehicle->imageUrl()"
            :alt="$vehicle->name"
            ratio="aspect-4/3 sm:aspect-16/9 lg:aspect-21/9"
            eager
        />
        @if (filled($vehicle->image_credit))
            <p class="mt-2 px-4 text-right text-[11px] text-neutral-400 sm:px-0">{{ $vehicle->image_credit }}</p>
        @endif
    </div>

    {{-- In-page navigation --}}
    <nav
        aria-label="{{ __('On this page') }}"
        class="sticky top-16 z-30 mt-10 border-y border-neutral-200 bg-white/95 backdrop-blur"
    >
        <div class="mx-auto flex max-w-360 items-center gap-6 overflow-x-auto px-4 text-sm whitespace-nowrap sm:px-6 lg:px-10">
            <span class="hidden py-4 font-medium md:inline">{{ $vehicle->name }}</span>
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" class="py-4 text-neutral-600 hover:text-neutral-950">{{ $label }}</a>
            @endforeach
            <a href="#enquire" class="text-volt-700 ml-auto py-4 font-medium">{{ __('Enquire') }}</a>
        </div>
    </nav>

    {{-- Key benefits --}}
    <section id="overview" class="mx-auto w-full max-w-360 scroll-mt-32 px-4 py-20 sm:px-6 lg:px-10 lg:py-28">
        <x-voltiva.section-heading :eyebrow="__('Why you will love it')" :title="$vehicle->summary" />

        @if (filled($vehicle->key_benefits))
            <div class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($vehicle->key_benefits as $benefit)
                    <div class="border-t border-neutral-950 pt-5">
                        <h3 class="text-lg font-medium">{{ $benefit['title'] ?? '' }}</h3>
                        <p class="mt-2 leading-relaxed text-neutral-600">{{ $benefit['body'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- At a glance: the benefit, then the technical detail. --}}
        <dl class="mt-20 grid border-t border-neutral-200 sm:grid-cols-2 lg:grid-cols-4">
            <div class="border-b border-neutral-200 py-8 sm:pr-8 lg:border-r">
                <dt class="text-sm text-neutral-500">{{ __('More range between charges') }}</dt>
                <dd class="mt-2 text-3xl font-medium tracking-tight">
                    {{ __('Up to :km km', ['km' => $vehicle->range_km]) }}
                </dd>
                <dd class="mt-2 text-sm text-neutral-600">
                    {{ $vehicle->battery_voltage }}V {{ $vehicle->battery_capacity_ah }}<x-voltiva.term key="ah" />
                    <x-voltiva.term key="lifepo4" :label="$vehicle->battery_chemistry" />
                    · {{ $vehicle->figure('battery_kwh') }} <x-voltiva.term key="kwh" />
                </dd>
            </div>
            <div class="border-b border-neutral-200 py-8 sm:pl-8 lg:border-r lg:pr-8">
                <dt class="text-sm text-neutral-500">{{ __('Charges overnight at home') }}</dt>
                <dd class="mt-2 text-3xl font-medium tracking-tight">
                    {{ __(':hours hours', ['hours' => $vehicle->figure('charge_hours')]) }}
                </dd>
                <dd class="mt-2 text-sm text-neutral-600">
                    {{ __('From empty to full on a normal 230V household socket') }}
                </dd>
            </div>
            <div class="border-b border-neutral-200 py-8 sm:pr-8 lg:border-r lg:pl-8">
                <dt class="text-sm text-neutral-500">
                    {{ $vehicle->top_speed_kmh >= 70 ? __('Keeps up with island traffic') : __('Made for town and village streets') }}
                </dt>
                <dd class="mt-2 text-3xl font-medium tracking-tight">
                    {{ __(':speed km/h', ['speed' => $vehicle->top_speed_kmh]) }}
                </dd>
                <dd class="mt-2 text-sm text-neutral-600">
                    {{ __(':kw kW electric motor', ['kw' => $vehicle->figure('motor_kw')]) }} ·
                    <x-voltiva.term :key="$category->glossaryKey()" :label="$category->label()" />
                </dd>
            </div>
            <div class="border-b border-neutral-200 py-8 sm:pl-8">
                <dt class="text-sm text-neutral-500">{{ __('Parks where others cannot') }}</dt>
                <dd class="mt-2 text-3xl font-medium tracking-tight">
                    {{ number_format($vehicle->length_mm / 1000, 2) }} m
                </dd>
                <dd class="mt-2 text-sm text-neutral-600">
                    {{ trans_choice(':count seat|:count seats', $vehicle->seats, ['count' => $vehicle->seats]) }} · {{ $vehicle->formattedDimensions() }}
                </dd>
            </div>
        </dl>

        @if ($vehicle->renderedDescription() !== '')
            <x-voltiva.prose class="mt-16 max-w-3xl">{!! $vehicle->renderedDescription() !!}</x-voltiva.prose>
        @endif
    </section>

    {{-- Photos and video: fixed areas, whatever is uploaded. --}}
    @if ($video !== null || $gallery !== [])
        <section class="mx-auto grid w-full max-w-360 gap-4 sm:px-6 lg:px-10 {{ count($gallery) + ($video ? 1 : 0) > 1 ? 'md:grid-cols-2' : '' }}">
            @if ($video !== null)
                <x-voltiva.video
                    :embed="$video"
                    :poster="$gallery[0] ?? $vehicle->imageUrl()"
                    :title="$vehicle->name"
                />
            @endif
            @foreach (array_slice($gallery, 0, $video ? 1 : 2) as $photo)
                <x-voltiva.image :src="$photo" :alt="$vehicle->name" ratio="aspect-video" />
            @endforeach
        </section>
    @endif

    {{-- Battery, range and charging --}}
    <section id="battery" class="mx-auto w-full max-w-360 scroll-mt-32 px-4 py-20 sm:px-6 lg:px-10 lg:py-28">
        <div class="grid gap-16 lg:grid-cols-2">
            <div>
                <x-voltiva.section-heading
                    :eyebrow="__('Battery & range')"
                    :title="__('Up to :km km on a single charge', ['km' => $vehicle->range_km])"
                />
                <div class="mt-6 space-y-4 text-lg leading-relaxed text-neutral-600">
                    <p>
                        {{ __('Enough for most people’s whole week around town, or a day trip across the island and back.') }}
                    </p>
                    <p class="text-base">
                        {{ $vehicle->batteryDescription() }} · {{ $vehicle->figure('battery_kwh') }} kWh.
                        <x-voltiva.term key="lifepo4" :label="__('What is LiFePO4?')" />
                    </p>
                    <p class="text-base">
                        {{ __('Covered by a :count-year battery warranty.', ['count' => $vehicle->battery_warranty_years]) }}
                    </p>
                </div>
                <a
                    href="{{ route('pages.show', 'batteries-and-range') }}"
                    class="hover:text-volt-700 mt-6 inline-flex items-center gap-1 text-sm font-medium"
                >
                    {{ __('How batteries and range work') }} <flux:icon.arrow-right variant="micro" />
                </a>
            </div>
            <div>
                <x-voltiva.section-heading :eyebrow="__('Charging')" :title="__('Plug in at home. That is it.')" />
                <div class="mt-6 space-y-4 text-lg leading-relaxed text-neutral-600">
                    <p>
                        {{ __('Charges from any normal 230V household socket – no wallbox or installation needed.') }}
                    </p>
                    <ul class="space-y-3 text-base">
                        <li class="flex gap-3">
                            <flux:icon.bolt variant="micro" class="text-volt-600 mt-1.5" />
                            {{ __('Empty to full in about :hours hours – overnight', ['hours' => $vehicle->figure('charge_hours')]) }}
                        </li>
                        <li class="flex gap-3">
                            <flux:icon.currency-euro variant="micro" class="text-volt-600 mt-1.5" />
                            {{ __('A full charge costs about :cost at a typical home tariff', ['cost' => $fullChargeCost]) }}
                        </li>
                        <li class="flex gap-3">
                            <flux:icon.home variant="micro" class="text-volt-600 mt-1.5" />
                            {{ __('A portable charging cable is included') }}
                        </li>
                    </ul>
                </div>
                <a
                    href="{{ route('pages.show', 'charging') }}"
                    class="hover:text-volt-700 mt-6 inline-flex items-center gap-1 text-sm font-medium"
                >
                    {{ __('Charging explained') }} <flux:icon.arrow-right variant="micro" />
                </a>
            </div>
        </div>
    </section>

    {{-- Interior, comfort and safety --}}
    @if (filled($vehicle->comfort) || filled($vehicle->safety))
        <section id="comfort" class="scroll-mt-32 bg-neutral-50 py-20 lg:py-28">
            <div class="mx-auto grid max-w-360 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-20 lg:px-10">
                <x-voltiva.image
                    :src="$gallery[count($gallery) - 1] ?? $vehicle->imageUrl()"
                    :alt="__('Inside the :name', ['name' => $vehicle->name])"
                    ratio="aspect-4/3"
                />
                <div class="space-y-12">
                    @if (filled($vehicle->comfort))
                        <div>
                            <x-voltiva.section-heading
                                :eyebrow="__('Interior & comfort')"
                                :title="__('Comfortable for every trip')"
                            />
                            <p class="mt-5 text-lg leading-relaxed text-neutral-600">{{ $vehicle->comfort }}</p>
                        </div>
                    @endif
                    @if (filled($vehicle->safety))
                        <div>
                            <x-voltiva.section-heading :eyebrow="__('Safety')" :title="__('Built to EU standards')" />
                            <p class="mt-5 text-lg leading-relaxed text-neutral-600">{{ $vehicle->safety }}</p>
                            <p class="mt-3 text-sm text-neutral-500">
                                <x-voltiva.term key="type-approval" /> ·
                                <x-voltiva.term :key="$category->glossaryKey()" :label="$category->label()" />
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Specifications --}}
    <section id="specifications" class="mx-auto w-full max-w-360 scroll-mt-32 px-4 py-20 sm:px-6 lg:px-10 lg:py-28">
        <div class="grid gap-12 lg:grid-cols-[1fr_2fr]">
            <x-voltiva.section-heading
                :eyebrow="__('Specifications')"
                :title="__('The details')"
                :intro="__('Tap any underlined term for a plain-English explanation.')"
            />

            <div>
                <dl class="divide-y divide-neutral-200 border-y border-neutral-200 text-sm sm:text-base">
                    @php
                        $specs = [
                            [__('Price'), $vehicle->formattedPrice()],
                            [__('Top speed'), __(':speed km/h', ['speed' => $vehicle->top_speed_kmh])],
                            [__('Range'), __('Up to :km km', ['km' => $vehicle->range_km])],
                            [__('Battery energy'), $vehicle->figure('battery_kwh').' kWh'],
                            [__('Charging time (230V)'), __(':hours hours', ['hours' => $vehicle->figure('charge_hours')])],
                            [__('Motor'), __(':kw kW electric motor', ['kw' => $vehicle->figure('motor_kw')])],
                            [__('Seats'), (string) $vehicle->seats],
                            [__('Length × width × height'), $vehicle->formattedDimensions()],
                            [__('Weight'), $vehicle->kerb_weight_kg.' kg'],
                            [__('Warranty'), trans_choice(':count year|:count years', $vehicle->warranty_years, ['count' => $vehicle->warranty_years])],
                            [__('Battery warranty'), trans_choice(':count year|:count years', $vehicle->battery_warranty_years, ['count' => $vehicle->battery_warranty_years])],
                            [__('Licence needed'), __($category->licence())],
                        ];
                    @endphp

                    <div class="grid grid-cols-2 gap-4 py-4">
                        <dt class="text-neutral-500">{{ __('Vehicle class') }}</dt>
                        <dd>
                            <x-voltiva.term :key="$category->glossaryKey()" :label="$category->label()" />
                            – {{ __($category->description()) }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-2 gap-4 py-4">
                        <dt class="text-neutral-500">{{ __('Battery') }}</dt>
                        <dd>
                            {{ $vehicle->battery_voltage }}V {{ $vehicle->battery_capacity_ah }}<x-voltiva.term
                                key="ah"
                            />
                            <x-voltiva.term key="lifepo4" :label="$vehicle->battery_chemistry" />
                        </dd>
                    </div>
                    @foreach ($specs as [$label, $value])
                        <div class="grid grid-cols-2 gap-4 py-4">
                            <dt class="text-neutral-500">{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if (filled($vehicle->equipment))
                    <h3 class="mt-12 text-lg font-medium">{{ __('Standard equipment') }}</h3>
                    <ul class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ($vehicle->equipment as $item)
                            <li class="flex gap-3 text-neutral-700">
                                <flux:icon.check variant="micro" class="text-volt-600 mt-1 shrink-0" />
                                @if (str_contains($item, 'EPS'))
                                    <span>{{ $item }} <x-voltiva.term key="eps" :label="__('(what is EPS?)')" /></span>
                                @else
                                    <span>{{ $item }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- Registration, servicing, finance, accessories --}}
    <section id="ownership" class="scroll-mt-32 bg-neutral-950 py-20 text-white lg:py-28">
        <div class="mx-auto max-w-360 px-4 sm:px-6 lg:px-10">
            <p class="text-volt-500 text-xs font-medium tracking-[0.2em] uppercase">{{ __('Ownership') }}</p>
            <h2 class="mt-3 max-w-3xl text-3xl font-medium tracking-tight text-balance sm:text-4xl">
                {{ __('What happens after you buy') }}
            </h2>

            <div class="mt-14 grid gap-12 lg:grid-cols-[1fr_1fr_1.3fr]">
                <div class="space-y-12">
                    <div>
                        <h3 class="text-lg font-medium">{{ __('Registration') }}</h3>
                        <p class="mt-3 leading-relaxed text-neutral-300">
                            {{ __('We register the car with the DGT and fit your plates, so it arrives ready to drive. You will need: :licence.', ['licence' => __($category->licence())]) }}
                        </p>
                        <p class="mt-3 text-sm text-neutral-400">
                            <x-voltiva.term key="coc" /> · <x-voltiva.term key="vin" /> ·
                            <x-voltiva.term key="type-approval" />
                        </p>
                        <a
                            href="{{ route('pages.show', 'registration') }}"
                            class="hover:text-volt-500 mt-4 inline-flex items-center gap-1 text-sm font-medium text-white"
                            >{{ __('How registration works') }} <flux:icon.arrow-right variant="micro"
                        /></a>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium">{{ __('Servicing') }}</h3>
                        <p class="mt-3 leading-relaxed text-neutral-300">
                            {{ __('A :years-year warranty and a :battery-year battery warranty, with servicing in Mallorca and collection from your home.', ['years' => $vehicle->warranty_years, 'battery' => $vehicle->battery_warranty_years]) }}
                        </p>
                        <a
                            href="{{ route('pages.show', 'servicing-and-support') }}"
                            class="hover:text-volt-500 mt-4 inline-flex items-center gap-1 text-sm font-medium text-white"
                            >{{ __('Servicing & Support') }} <flux:icon.arrow-right variant="micro"
                        /></a>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-medium">{{ __('Accessories') }}</h3>
                    <ul class="mt-3 space-y-2 text-neutral-300">
                        @foreach ([__('Weatherproof car cover'), __('Longer charging cable'), __('All-weather floor mats'), __('Roof bars and bike carrier')] as $accessory)
                            <li class="flex gap-3">
                                <flux:icon.plus variant="micro" class="text-volt-500 mt-1" /> {{ $accessory }}
                            </li>
                        @endforeach
                    </ul>
                    <a
                        href="{{ route('pages.show', 'accessories') }}"
                        class="hover:text-volt-500 mt-4 inline-flex items-center gap-1 text-sm font-medium text-white"
                        >{{ __('All accessories') }} <flux:icon.arrow-right variant="micro"
                    /></a>
                </div>

                {{-- Finance estimate: the same amortisation as App\Voltiva\Money::monthlyPayment(), in the browser. --}}
                <div
                    class="bg-white p-6 text-neutral-950 sm:p-8"
                    x-data="{
                        price: {{ $vehicle->price_cents / 100 }},
                        deposit: {{ (int) $finance['default_deposit_percent'] }},
                        term: {{ (int) $finance['default_term'] }},
                        apr: {{ (float) $finance['apr'] }},
                        money(value) {
                            return new Intl.NumberFormat(@js(app()->getLocale()), { style: 'currency', currency: @js(config('voltiva.currency')), maximumFractionDigits: 0 }).format(value)
                        },
                        get borrowed() { return this.price * (1 - this.deposit / 100) },
                        get monthly() {
                            const rate = this.apr / 100 / 12
                            return this.borrowed * rate / (1 - Math.pow(1 + rate, -this.term))
                        },
                    }"
                >
                    <h3 class="text-lg font-medium">{{ __('Finance estimate') }}</h3>
                    <p class="mt-1 text-sm text-neutral-600">
                        {{ __('See roughly what :name would cost each month.', ['name' => $vehicle->name]) }}
                    </p>

                    <label class="mt-6 block text-sm">
                        <span class="flex justify-between"><span>{{ __('Deposit') }}</span>
                            <span class="font-medium" x-text="deposit + '% · ' + money((price * deposit) / 100)"></span
                        ></span>
                        <input
                            type="range"
                            min="0"
                            max="50"
                            step="5"
                            x-model.number="deposit"
                            class="mt-2 w-full accent-neutral-950"
                        />
                    </label>

                    <fieldset class="mt-5">
                        <legend class="text-sm">{{ __('Term') }}</legend>
                        <div class="mt-2 grid grid-cols-4 gap-2">
                            @foreach ($finance['terms'] as $months)
                                <button
                                    type="button"
                                    @click="term = {{ $months }}"
                                    :class="term === {{ $months }} ? 'bg-neutral-950 text-white border-neutral-950' : 'border-neutral-300 hover:border-neutral-950'"
                                    class="border py-2 text-sm transition"
                                >
                                    {{ __(':months mo', ['months' => $months]) }}
                                </button>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="mt-6 border-t border-neutral-200 pt-5">
                        <p class="text-sm text-neutral-600">{{ __('Estimated monthly payment') }}</p>
                        <p class="text-4xl font-medium tracking-tight" x-text="money(monthly)"></p>
                        <p class="mt-2 text-xs leading-relaxed text-neutral-500">
                            {{ __('Illustration only, at :apr% APR representative. Not an offer of credit; finance is subject to status.', ['apr' => $finance['apr']]) }}
                        </p>
                    </div>

                    <x-voltiva.button :href="route('enquiry', ['vehicle' => $vehicle->slug])" class="mt-6 w-full">
                        {{ __('Ask for a finance quote') }}</x-voltiva.button>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    @if (filled($vehicle->faqs))
        <section id="faq" class="mx-auto w-full max-w-360 scroll-mt-32 px-4 py-20 sm:px-6 lg:px-10 lg:py-28">
            <div class="grid gap-12 lg:grid-cols-[1fr_2fr]">
                <x-voltiva.section-heading
                    :eyebrow="__('FAQ')"
                    :title="__('Questions about the :name', ['name' => $vehicle->name])"
                />
                <div class="divide-y divide-neutral-200 border-y border-neutral-200">
                    @foreach ($vehicle->faqs as $faq)
                        <details class="group py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-medium">
                                {{ $faq['question'] ?? '' }}
                                <flux:icon.plus class="size-5 shrink-0 transition group-open:rotate-45" />
                            </summary>
                            <p class="mt-3 max-w-2xl leading-relaxed text-neutral-600">{{ $faq['answer'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Advice, then the rest of the range --}}
    @if ($articles->isNotEmpty())
        <section class="border-t border-neutral-200">
            <div class="mx-auto w-full max-w-360 px-4 py-20 sm:px-6 lg:px-10">
                <x-voltiva.section-heading :eyebrow="__('News & Advice')" :title="__('Helpful reading')" />
                <div class="mt-10 grid gap-x-8 gap-y-12 md:grid-cols-3">
                    @foreach ($articles as $article)
                        <x-voltiva.article-card :article="$article" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($otherVehicles->isNotEmpty())
        <section class="bg-neutral-50">
            <div class="mx-auto w-full max-w-360 px-4 py-20 sm:px-6 lg:px-10">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <x-voltiva.section-heading :eyebrow="__('Cars')" :title="__('You might also like')" />
                    <a
                        href="{{ route('compare', ['cars' => [$vehicle->slug, ...$otherVehicles->pluck('slug')->all()]]) }}"
                        class="hover:text-volt-700 text-sm font-medium"
                    >{{ __('Compare these cars') }}</a>
                </div>
                <div class="mt-10 grid gap-x-8 gap-y-14 md:grid-cols-3">
                    @foreach ($otherVehicles as $other)
                        <x-voltiva.vehicle-card :vehicle="$other" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-voltiva.enquiry-section
        :vehicle="$vehicle"
        source="vehicle"
        :title="__('Interested in the :name?', ['name' => $vehicle->name])"
    />
</x-layouts::public>
