@props([
    'compact' => false,
])

{{--
    The public site's footer, built from the business details settings. The
    contact block renders only the details an administrator has filled in, so
    an unset address, phone, or email simply does not appear. Styling beyond
    the internal structure (borders, spacing, colours) comes from the caller
    through the attribute bag.

    The full variant sits on the public layout's dark band, so its colours are
    fixed light-on-dark in both schemes. The compact variant is the footer of
    the auth pages (layouts/auth/split.blade.php passes compact), and a row of
    links beside a login form is clutter in the one place a visitor is trying
    to do a single thing.
--}}
@if ($compact)
    <footer {{ $attributes->merge(['class' => 'text-sm']) }}>
        <div class="flex flex-col items-center gap-2 text-center">
            <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $businessName }}</p>

            @if ($businessPhone !== '' || $businessEmail !== '')
                <address class="not-italic leading-relaxed">
                    @if ($businessPhone !== '')
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}" class="block hover:underline">{{ $businessPhone }}</a>
                    @endif

                    @if ($businessEmail !== '')
                        <a href="mailto:{{ $businessEmail }}" class="block hover:underline">{{ $businessEmail }}</a>
                    @endif
                </address>
            @endif
        </div>
    </footer>
@else
    <footer {{ $attributes->merge(['class' => 'text-sm text-neutral-400']) }}>
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-2.5">
                    <x-app-logo-icon class="size-9" />
                    <p class="font-display text-xl font-semibold text-white">{{ $businessName }}</p>
                </div>
                <p class="mt-4 max-w-sm leading-relaxed">
                    {{ __('Small-group tours and tailor-made journeys, planned by people who have walked every route.') }}
                </p>
                <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
                    <span>{{ __('Licensed and bonded tour operator') }}</span>
                    <span>{{ __('Member, Adventure Travel Trade Association') }}</span>
                </p>
            </div>

            <nav aria-label="{{ __('Footer') }}">
                <p class="font-semibold text-white">{{ __('Explore') }}</p>
                <ul class="mt-3 space-y-2">
                    <li><a href="{{ route('destinations.index') }}" class="hover:text-white" wire:navigate>{{ __('Destinations') }}</a></li>
                    <li><a href="{{ route('tours.index') }}" class="hover:text-white" wire:navigate>{{ __('All tours') }}</a></li>
                    <li><a href="{{ route('plan-trip') }}" class="hover:text-white" wire:navigate>{{ __('Tailor-made trips') }}</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white" wire:navigate>{{ __('Contact') }}</a></li>
                    @foreach ($footerPages() as $footerPage)
                        <li><a href="{{ route('pages.show', $footerPage) }}" class="hover:text-white" wire:navigate>{{ $footerPage->title }}</a></li>
                    @endforeach
                </ul>
            </nav>

            @if ($businessAddress !== '' || $businessPhone !== '' || $businessEmail !== '')
                <div>
                    <p class="font-semibold text-white">{{ __('Talk to a trip specialist') }}</p>
                    <address class="mt-3 space-y-2 not-italic leading-relaxed">
                        @if ($businessPhone !== '')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}" class="block hover:text-white">{{ $businessPhone }}</a>
                        @endif

                        @if ($businessEmail !== '')
                            <a href="mailto:{{ $businessEmail }}" class="block hover:text-white">{{ $businessEmail }}</a>
                        @endif

                        @if ($businessAddress !== '')
                            <span class="block whitespace-pre-line">{{ $businessAddress }}</span>
                        @endif
                    </address>
                </div>
            @endif
        </div>

        <p class="mt-10 border-t border-neutral-800 pt-6 text-xs text-neutral-500">
            {{ __('© :year :business. Prices are per person in USD, based on twin share.', ['year' => now()->year, 'business' => $businessName]) }}
        </p>
    </footer>
@endif
