@php($package = $this->package)

{{--
    The product page for one package. The description is Markdown rendered by
    Package::renderedDescription(), which escapes any HTML in the source -- the
    same contract as Page::renderedBody(), and the reason the {!! !!} is safe.
--}}
<main class="flex-1 py-10">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-neutral-500 dark:text-neutral-400">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('shop.index') }}" wire:navigate class="hover:text-neutral-900 hover:underline dark:hover:text-white">{{ __('All packages') }}</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('shop.index', ['region' => $package->region->value]) }}" wire:navigate class="hover:text-neutral-900 hover:underline dark:hover:text-white">{{ __($package->region->label()) }}</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-neutral-900 dark:text-white">{{ $package->title }}</li>
        </ol>
    </nav>

    @unless ($package->is_active)
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            {{ __('This package is not on sale. Only people who can edit it see this page.') }}
        </div>
    @endunless

    <div class="mt-6 grid gap-10 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <figure class="overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-100 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="relative aspect-16/10">
                    @if ($package->imageUrl() !== null)
                        <img
                            src="{{ $package->imageUrl() }}"
                            alt="{{ __('Aerial view of :location', ['location' => $package->location]) }}"
                            class="size-full object-cover"
                        />
                    @endif

                    <span class="absolute bottom-3 left-3 rounded-full bg-neutral-950/70 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                        {{ __('Preview still') }}
                    </span>
                </div>

                @if (filled($package->image_credit))
                    <figcaption class="px-4 py-2 text-xs text-neutral-500 dark:text-neutral-400">{{ $package->image_credit }}</figcaption>
                @endif
            </figure>

            <section class="mt-10" aria-labelledby="about-heading">
                <h2 id="about-heading" class="text-xl font-semibold tracking-tight">{{ __('About this package') }}</h2>

                <div class="[&_li]:my-1 [&_p]:my-4 [&_strong]:font-semibold [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 mt-2 text-base leading-relaxed text-neutral-700 dark:text-neutral-300">
                    {!! $package->renderedDescription() !!}
                </div>
            </section>

            <section class="mt-10" aria-labelledby="licence-heading">
                <h2 id="licence-heading" class="text-xl font-semibold tracking-tight">{{ __('What your licence covers') }}</h2>

                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        __('Royalty-free commercial use, worldwide and forever'),
                        __('Web, social, broadcast and paid advertising'),
                        __('Unlimited edits, cuts and colour grades'),
                        __('Original files with no watermark'),
                    ] as $benefit)
                        <li class="flex items-start gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            {{ $benefit }}
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="lg:col-span-2">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm lg:sticky lg:top-8 dark:border-neutral-800 dark:bg-neutral-900">
                <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-400">
                    {{ $package->location }} · {{ $package->country }}
                </p>

                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-balance">{{ $package->title }}</h1>

                <p class="mt-3 text-neutral-600 dark:text-neutral-400">{{ $package->summary }}</p>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <p class="text-3xl font-semibold">{{ $package->formattedPrice() }}</p>
                    <x-shop.availability :package="$package" />
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-neutral-200 bg-neutral-200 text-sm dark:border-neutral-800 dark:bg-neutral-800">
                    @foreach ([
                        __('Resolution') => $package->resolution,
                        __('Frame rate') => $package->frame_rate.' fps',
                        __('Clips') => $package->clip_count,
                        __('Running time') => $package->formattedDuration(),
                    ] as $label => $value)
                        <div class="bg-white p-3 dark:bg-neutral-900">
                            <dt class="text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                    @if ($package->isSoldOut())
                        {{ __('All licences for this package have been sold.') }}
                    @elseif ($package->stock === null)
                        {{ __('Available now. Instant download after checkout.') }}
                    @else
                        {{ trans_choice(':count licence available. Instant download after checkout.|:count licences available. Instant download after checkout.', $package->stock, ['count' => $package->stock]) }}
                    @endif
                </p>

                <div class="mt-6 grid gap-3">
                    @if ($package->isAvailable())
                        @if ($this->inCart)
                            <flux:button variant="primary" icon="shopping-bag" :href="route('cart')" wire:navigate>
                                {{ __('In your basket — check out') }}
                            </flux:button>
                        @else
                            <flux:button variant="primary" icon="bolt" wire:click="buyNow">
                                {{ __('Buy now') }}
                            </flux:button>
                            <flux:button icon="shopping-bag" wire:click="addToCart">
                                {{ __('Add to basket') }}
                            </flux:button>
                        @endif
                    @else
                        <flux:button disabled>{{ __('Sold out') }}</flux:button>
                        <flux:button variant="ghost" :href="route('contact')" wire:navigate>
                            {{ __('Ask about a custom shoot') }}
                        </flux:button>
                    @endif
                </div>

                <ul class="mt-6 space-y-2 border-t border-neutral-200 pt-6 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-400">
                    <li class="flex items-center gap-2"><flux:icon.lock-closed variant="micro" /> {{ __('Secure checkout') }}</li>
                    <li class="flex items-center gap-2"><flux:icon.arrow-down-tray variant="micro" /> {{ __('Download links delivered instantly') }}</li>
                    <li class="flex items-center gap-2"><flux:icon.document-text variant="micro" /> {{ __('Licence certificate with every order') }}</li>
                </ul>
            </div>
        </aside>
    </div>

    @if ($this->related->isNotEmpty())
        <section class="mt-20" aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-2xl font-semibold tracking-tight">{{ __('You may also like') }}</h2>

            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->related as $related)
                    <x-shop.package-card :package="$related" wire:key="related-{{ $related->id }}" />
                @endforeach
            </div>
        </section>
    @endif
</main>
