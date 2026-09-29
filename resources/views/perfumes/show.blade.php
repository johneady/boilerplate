<x-layouts::public :title="__(':name by :brand', ['name' => $perfume->name, 'brand' => $perfume->brand->name])" :description="$perfume->description">
    <main class="flex-1 py-10">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('perfumes.index') }}" class="hover:text-plum-700 dark:hover:text-plum-300" wire:navigate>{{ __('Perfumes') }}</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('perfumes.index', ['brand' => $perfume->brand->slug]) }}" class="hover:text-plum-700 dark:hover:text-plum-300" wire:navigate>{{ $perfume->brand->name }}</a>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <p class="text-sm font-semibold tracking-widest text-plum-600 uppercase dark:text-plum-300">
                    {{ $perfume->brand->name }}
                    @if ($perfume->brand->country)
                        <span class="font-normal tracking-normal text-neutral-500 normal-case dark:text-neutral-400">· {{ $perfume->brand->country }}</span>
                    @endif
                </p>

                <h1 class="mt-2 font-display text-5xl leading-none font-semibold tracking-tight text-balance sm:text-6xl">
                    {{ $perfume->name }}
                </h1>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    @if ($perfume->family !== null)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold text-white" style="background-color: {{ $perfume->family->color() }}">
                            {{ __($perfume->family->label()) }}
                        </span>
                    @endif
                    @if ($perfume->concentration !== null)
                        <flux:badge size="sm">{{ __($perfume->concentration->label()) }}</flux:badge>
                    @endif
                    @if ($perfume->gender !== null)
                        <flux:badge size="sm">{{ __($perfume->gender->label()) }}</flux:badge>
                    @endif
                    @if ($perfume->release_year)
                        <flux:badge size="sm">{{ $perfume->release_year }}</flux:badge>
                    @endif
                </div>

                @if ($perfume->description)
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-neutral-700 dark:text-neutral-300">
                        {{ $perfume->description }}
                    </p>
                @endif

                @if ($perfume->perfumer)
                    <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                        <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Perfumer') }}:</span>
                        {{ $perfume->perfumer }}
                    </p>
                @endif

                <div class="mt-8">
                    <livewire:perfumes.follow-button :perfume="$perfume" />
                </div>

                <p class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ trans_choice(':count page view|:count page views', $totalViews, ['count' => number_format($totalViews)]) }}
                </p>
            </div>

            {{-- The note pyramid: top notes are what you smell first, the base what lingers. --}}
            <section class="lg:col-span-2" aria-labelledby="pyramid-heading">
                <h2 id="pyramid-heading" class="font-display text-2xl font-semibold">{{ __('Notes') }}</h2>

                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['label' => __('Top'), 'hint' => __('The first impression'), 'notes' => $perfume->top_notes, 'width' => 'mx-auto w-3/5', 'tint' => 'bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-900/50'],
                        ['label' => __('Heart'), 'hint' => __('The character, after a few minutes'), 'notes' => $perfume->heart_notes, 'width' => 'mx-auto w-4/5', 'tint' => 'bg-plum-50 border-plum-200 dark:bg-plum-950/40 dark:border-plum-800/60'],
                        ['label' => __('Base'), 'hint' => __('What lingers for hours'), 'notes' => $perfume->base_notes, 'width' => 'w-full', 'tint' => 'bg-stone-100 border-stone-200 dark:bg-stone-900/60 dark:border-stone-800'],
                    ] as $tier)
                        <div class="{{ $tier['width'] }} rounded-xl border p-4 text-center {{ $tier['tint'] }}">
                            <p class="text-xs font-semibold tracking-widest text-neutral-700 uppercase dark:text-neutral-200">{{ $tier['label'] }}</p>
                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $tier['hint'] }}</p>
                            <p class="mt-2 text-sm text-neutral-800 dark:text-neutral-100">
                                @forelse ($tier['notes'] ?? [] as $note)
                                    <a href="{{ route('perfumes.index', ['q' => $note]) }}" class="hover:text-plum-700 hover:underline dark:hover:text-plum-300" wire:navigate>{{ $note }}</a>@unless ($loop->last), @endunless
                                @empty
                                    <span class="text-neutral-400">{{ __('Not listed') }}</span>
                                @endforelse
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        @if ($similar->isNotEmpty())
            <section class="mt-20">
                <h2 class="font-display text-3xl font-semibold">{{ __('More in this family') }}</h2>

                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($similar as $other)
                        <x-perfumes.card :perfume="$other" />
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts::public>
