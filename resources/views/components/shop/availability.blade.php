@props(['package', 'solid' => false])

{{--
    The stock badge shown on a package card and its product page. Renders
    nothing for a package with plenty of licences left: "in stock" on every
    card is noise, so only the states a buyer should act on are called out.

    `solid` is for a badge laid over a photo, where the default tinted badge
    has too little contrast to read.
--}}
@if ($package->isSoldOut())
    <flux:badge size="sm" color="red" :variant="$solid ? 'solid' : null">{{ __('Sold out') }}</flux:badge>
@elseif ($package->isLowStock())
    <flux:badge size="sm" color="amber" :variant="$solid ? 'solid' : null">
        {{ trans_choice('Only :count licence left|Only :count licences left', $package->stock, ['count' => $package->stock]) }}
    </flux:badge>
@endif
