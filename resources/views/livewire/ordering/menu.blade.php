{{--
    The home page, which is the menu (App\Livewire\Ordering\Menu).

    Every section is rendered and the category chips just hide the others with
    Alpine, so the whole menu is crawlable and filtering needs no round trip.
--}}
<main class="flex-1">
    {{-- Hero --}}
    <section class="grid items-center gap-10 py-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14 lg:py-14">
        <div>
            <p class="inline-flex items-center gap-2 rounded-full border border-emerald-800/15 bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-900 uppercase dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                <span class="size-1.5 rounded-full bg-orange-500"></span>
                {{ __('Order online · Pickup or delivery') }}
            </p>

            <h1 class="font-display mt-6 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">
                {{ __('Fresh from our oven,') }}
                <span class="text-emerald-800 italic dark:text-emerald-400">{{ __('ready when you are.') }}</span>
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-relaxed text-stone-600 dark:text-stone-400">
                {{ __(':business bakes bread, pastries and cakes every morning on Juniper Street. Pick what you fancy, choose a time, and we will have it boxed and waiting.', ['business' => $businessName]) }}
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a
                    href="#menu"
                    class="inline-flex items-center gap-2 rounded-full bg-emerald-900 px-6 py-3 font-semibold text-white shadow-md shadow-emerald-950/20 hover:bg-emerald-800 dark:bg-emerald-400 dark:text-stone-950 dark:hover:bg-emerald-300"
                >
                    {{ __('Start your order') }}
                    <flux:icon.arrow-down variant="micro" />
                </a>
                <a
                    href="{{ route('checkout') }}"
                    class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white/70 px-6 py-3 font-semibold text-stone-800 hover:border-stone-400 hover:bg-white dark:border-white/15 dark:bg-white/5 dark:text-stone-100 dark:hover:bg-white/10"
                    wire:navigate
                >
                    {{ __('View cart') }}
                </a>
            </div>

            <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-stone-600 dark:text-stone-400">
                @foreach ([
                    ['icon' => 'clock', 'text' => __('Ready in 30 minutes')],
                    ['icon' => 'truck', 'text' => __('Free delivery over :amount', ['amount' => \App\Ordering\Price::format((int) config('ordering.free_delivery_from_cents'))])],
                    ['icon' => 'banknotes', 'text' => __('Pay when you collect')],
                ] as $promise)
                    <li class="flex items-center gap-2">
                        <flux:icon :icon="$promise['icon']" variant="mini" class="text-orange-500" />
                        {{ $promise['text'] }}
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($this->featured->isNotEmpty())
            <div class="relative mx-auto w-full max-w-lg lg:max-w-none">
                <div class="grid grid-cols-5 grid-rows-2 gap-3 sm:gap-4">
                    @foreach ($this->featured as $index => $product)
                        <div
                            wire:key="featured-{{ $product->id }}"
                            @class([
                                'group relative overflow-hidden rounded-3xl bg-stone-200 shadow-lg shadow-emerald-950/10 dark:bg-stone-800',
                                'col-span-3 row-span-2' => $index === 0,
                                'col-span-2' => $index !== 0,
                            ])
                        >
                            @if ($product->imageUrl() !== null)
                                <img
                                    src="{{ $product->imageUrl() }}"
                                    alt="{{ $product->name }}"
                                    @class(['size-full object-cover', 'aspect-[3/4]' => $index === 0, 'aspect-square' => $index !== 0])
                                />
                            @endif
                            <span class="absolute inset-x-2 bottom-2 flex items-center justify-between gap-2 rounded-xl bg-white/90 px-3 py-1.5 text-xs font-semibold text-stone-900 backdrop-blur dark:bg-stone-950/80 dark:text-stone-100">
                                <span class="truncate">{{ $product->name }}</span>
                                <span class="text-emerald-800 dark:text-emerald-300">{{ $product->formattedPrice() }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- Menu --}}
    <section id="menu" class="scroll-mt-6 py-10" x-data="{ category: 'all' }">
        <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Today\'s menu') }}</h2>
                <p class="mt-2 max-w-xl text-stone-600 dark:text-stone-400">
                    {{ __('Add anything to your cart, then choose pickup or delivery at checkout.') }}
                </p>
            </div>

            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('Filter the menu') }}">
                @foreach (collect([['value' => 'all', 'label' => __('Everything')]])->merge($this->sections->map(fn ($section) => ['value' => $section['category']->value, 'label' => __($section['category']->label())])) as $chip)
                    <button
                        type="button"
                        wire:key="chip-{{ $chip['value'] }}"
                        x-on:click="category = '{{ $chip['value'] }}'"
                        x-bind:aria-pressed="category === '{{ $chip['value'] }}'"
                        x-bind:class="category === '{{ $chip['value'] }}' ? 'bg-emerald-900 text-white border-emerald-900 dark:bg-emerald-400 dark:text-stone-950' : 'bg-white/70 text-stone-700 hover:bg-white dark:bg-white/5 dark:text-stone-300'"
                        class="shrink-0 rounded-full border border-stone-200 px-4 py-1.5 text-sm font-medium whitespace-nowrap dark:border-white/10"
                    >
                        {{ $chip['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @forelse ($this->sections as $section)
            <div
                class="mt-10"
                x-show="category === 'all' || category === '{{ $section['category']->value }}'"
                wire:key="section-{{ $section['category']->value }}"
            >
                <h3 class="font-display text-2xl font-semibold">{{ __($section['category']->label()) }}</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($section['products'] as $product)
                        <x-ordering.product-card :product="$product" wire:key="product-{{ $product->id }}" />
                    @endforeach
                </div>
            </div>
        @empty
            <p class="mt-10 rounded-2xl border border-dashed border-stone-300 p-10 text-center text-stone-600 dark:border-white/15 dark:text-stone-400">
                {{ __('The menu is being updated. Please check back shortly.') }}
            </p>
        @endforelse
    </section>

    {{-- How it works --}}
    <section class="py-10">
        <div class="rounded-3xl bg-emerald-950 p-8 text-emerald-50 sm:p-10 dark:bg-emerald-400/10">
            <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('How ordering works') }}</h2>

            <ol class="mt-8 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['title' => __('Fill your cart'), 'body' => __('Browse the menu and add what you like. Change quantities any time before you check out.')],
                    ['title' => __('Pick a time'), 'body' => __('Choose pickup or delivery and a time from 30 minutes away. No account needed.')],
                    ['title' => __('Follow your order'), 'body' => __('Your order page updates as we prepare it, so you know exactly when it is ready.')],
                ] as $step)
                    <li>
                        <span class="font-display flex size-10 items-center justify-center rounded-full bg-orange-400 text-lg font-semibold text-stone-950">
                            {{ $loop->iteration }}
                        </span>
                        <h3 class="mt-4 text-lg font-semibold text-white">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-emerald-100/80">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        @php($credits = $this->sections->flatMap(fn ($section) => $section['products'])->filter(fn ($product) => filled($product->image_credit)))
        @if ($credits->isNotEmpty())
            <details class="mt-6 text-xs text-stone-500">
                <summary class="cursor-pointer">{{ __('Photo credits') }}</summary>
                <ul class="mt-2 space-y-1">
                    @foreach ($credits as $product)
                        <li wire:key="credit-{{ $product->id }}">{{ $product->name }}: {{ $product->image_credit }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
</main>
