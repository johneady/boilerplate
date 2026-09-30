@props(['product'])

{{-- One product on the menu: photo, name, price, description and the add button. --}}
<article
    {{ $attributes->class('group flex flex-col overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-sm shadow-stone-900/5 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-950/10 dark:border-white/10 dark:bg-stone-900') }}
    data-test="product"
>
    <div class="relative aspect-[4/3] overflow-hidden bg-stone-100 dark:bg-stone-800">
        @if ($product->imageUrl() !== null)
            <img
                src="{{ $product->imageUrl() }}"
                alt="{{ $product->name }}"
                loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105"
            />
        @endif

        @if ($product->is_featured)
            <span class="absolute top-3 left-3 rounded-full bg-emerald-950/80 px-2.5 py-1 text-xs font-semibold text-emerald-50 backdrop-blur">
                {{ __('Customer favourite') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-lg leading-snug font-semibold">{{ $product->name }}</h3>
            <p class="shrink-0 text-lg font-semibold text-emerald-800 dark:text-emerald-300">{{ $product->formattedPrice() }}</p>
        </div>

        <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{{ $product->description }}</p>

        <div class="mt-auto pt-5">
            <button
                type="button"
                wire:click="addToCart({{ $product->id }})"
                wire:loading.attr="disabled"
                wire:target="addToCart({{ $product->id }})"
                data-test="add-to-cart"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-emerald-800/20 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 transition hover:border-emerald-800 hover:bg-emerald-800 hover:text-white disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400 dark:hover:text-stone-950"
            >
                <flux:icon.plus variant="micro" wire:loading.remove wire:target="addToCart({{ $product->id }})" />
                <flux:icon.loading variant="micro" wire:loading wire:target="addToCart({{ $product->id }})" />
                {{ __('Add to cart') }}
            </button>
        </div>
    </div>
</article>
