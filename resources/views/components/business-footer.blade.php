@props([
    'compact' => false,
])

{{--
    The public site's footer, built from the business details settings. The
    contact block renders only the details an administrator has filled in, so
    an unset address, phone, or email simply does not appear. Styling beyond
    the internal structure (borders, spacing, colours) comes from the caller
    through the attribute bag.
--}}
<footer {{ $attributes->merge(['class' => 'text-sm']) }}>
    <div @class([
        'flex flex-col',
        'gap-4 sm:flex-row sm:items-end sm:justify-between' => ! $compact,
        'items-center gap-2 text-center' => $compact,
    ])>
        <div>
            <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $businessName }}</p>
            @unless ($compact)
                <p class="mt-1">{{ __('A division of nothing in particular.') }}</p>
            @endunless
        </div>

        @if ($businessAddress !== '' || $businessPhone !== '' || $businessEmail !== '')
            <address @class([
                'not-italic leading-relaxed',
                'sm:text-right' => ! $compact,
            ])>
                @if ($businessAddress !== '')
                    <span class="block whitespace-pre-line">{{ $businessAddress }}</span>
                @endif

                @if ($businessPhone !== '')
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}" class="block hover:underline">
                        {{ $businessPhone }}
                    </a>
                @endif

                @if ($businessEmail !== '')
                    <a href="mailto:{{ $businessEmail }}" class="block hover:underline">{{ $businessEmail }}</a>
                @endif
            </address>
        @endif
    </div>
</footer>
