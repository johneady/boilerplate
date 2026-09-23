<div>
    <flux:button
        :href="route('cart')"
        size="sm"
        variant="ghost"
        icon="shopping-bag"
        wire:navigate
        :aria-label="trans_choice('Basket, :count item|Basket, :count items', $count, ['count' => $count])"
    >
        {{ __('Basket') }}

        @if ($count > 0)
            <span
                class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-600 px-1.5 text-xs font-semibold text-white"
                data-test="cart-count"
            >
                {{ $count }}
            </span>
        @endif
    </flux:button>
</div>
