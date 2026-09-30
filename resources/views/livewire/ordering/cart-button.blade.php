{{-- The header's cart link. $count and $subtotal come from CartButton::render(). --}}
<a
    href="{{ route('checkout') }}"
    wire:navigate
    data-test="cart-button"
    class="relative inline-flex items-center gap-2 rounded-full bg-emerald-900 py-1.5 pr-3.5 pl-3 text-sm font-semibold text-white shadow-sm shadow-emerald-950/20 transition hover:bg-emerald-800 dark:bg-emerald-400 dark:text-stone-950 dark:hover:bg-emerald-300"
>
    <flux:icon.shopping-bag variant="mini" />
    <span class="max-sm:sr-only">{{ __('Cart') }}</span>
    <span
        class="inline-flex min-w-5 items-center justify-center rounded-full bg-orange-400 px-1.5 text-xs leading-5 font-bold text-stone-950"
        data-test="cart-count"
    >
        {{ $count }}
        <span class="sr-only">{{ trans_choice('item|items', $count) }}</span>
    </span>
    @if ($count > 0)
        <span class="font-normal text-emerald-100 max-md:hidden dark:text-emerald-950">{{ $subtotal }}</span>
    @endif
</a>
