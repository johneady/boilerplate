@props([
    'compact' => false,
])

{{--
    The public site's footer, built from the business details settings. The
    contact block renders only the details an administrator has filled in, so
    an unset address, phone, or email simply does not appear. Styling beyond
    the internal structure (borders, spacing, colours) comes from the caller
    through the attribute bag.

    The page links render only in the full variant. This component is also the
    footer of the auth pages (layouts/auth/split.blade.php passes compact), and a
    row of policy links beside a login form is clutter in the one place a visitor
    is trying to do a single thing.
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
                <p class="mt-1">{{ __('Free calculators for real estate agents. No sign-up, nothing stored.') }}</p>
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

    @unless ($compact)
        {{--
            Always rendered, because the Contact link is always in it: the
            contact route exists whether or not any page rows do.

            $footerPages is a closure, not a collection -- the composer defers
            the query so the compact variant above never runs it. Invoked here,
            which is the only place its result is needed.
        --}}
        <nav
            aria-label="{{ __('Footer') }}"
            class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-neutral-200 pt-4 dark:border-neutral-800"
        >
            @foreach ($footerPages() as $footerPage)
                <a href="{{ route('pages.show', $footerPage) }}" class="hover:underline" wire:navigate>
                    {{ $footerPage->title }}
                </a>
            @endforeach

            <a href="{{ route('contact') }}" class="hover:underline" wire:navigate>{{ __('Contact') }}</a>
        </nav>
    @endunless
</footer>
