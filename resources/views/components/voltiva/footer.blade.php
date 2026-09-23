{{--
    The site footer: the business details from the settings, the same three
    groups as the header, and the administrator's footer pages (privacy,
    terms, photo credits). $businessAddress, $businessPhone, $businessEmail and
    the $footerPages closure are composed in AppServiceProvider, shared with
    x-business-footer.

    The sign-in link lives here rather than in the header: customers do not
    have accounts, but the team signs in to the admin panel from the site.
--}}
<footer class="bg-neutral-100 text-sm text-neutral-600">
    <div class="mx-auto max-w-360 px-4 py-16 sm:px-6 lg:px-10">
        <div class="grid gap-12 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <p class="text-[13px] font-semibold tracking-[0.28em] text-neutral-950 uppercase">
                    {{ $businessName }}
                </p>
                <p class="mt-3 max-w-xs leading-relaxed">
                    {{ __('Electric Mobility Made Simple. Electric cars for everyday life in Mallorca.') }}
                </p>

                @if ($businessAddress !== '' || $businessPhone !== '' || $businessEmail !== '')
                    <address class="mt-6 space-y-1 leading-relaxed not-italic">
                        @if ($businessAddress !== '')
                            <span class="block whitespace-pre-line">{{ $businessAddress }}</span>
                        @endif
                        @if ($businessPhone !== '')
                            <a
                                href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}"
                                class="block hover:text-neutral-950"
                            >{{ $businessPhone }}</a>
                        @endif
                        @if ($businessEmail !== '')
                            <a
                                href="mailto:{{ $businessEmail }}"
                                class="block hover:text-neutral-950"
                            >{{ $businessEmail }}</a>
                        @endif
                    </address>
                @endif
            </div>

            <nav aria-label="{{ __('Cars') }}">
                <p class="font-medium text-neutral-950">{{ __('Cars') }}</p>
                <ul class="mt-4 space-y-2">
                    <li>
                        <a
                            href="{{ route('cars.index') }}"
                            class="hover:text-neutral-950"
                        >{{ __('All electric cars') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('cars.category', 'l6e') }}"
                            class="hover:text-neutral-950"
                        >{{ __('L6e Electric Cars') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('cars.category', 'l7e') }}"
                            class="hover:text-neutral-950"
                        >{{ __('L7e Electric Cars') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('compare') }}" class="hover:text-neutral-950">{{ __('Compare Cars') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('finder') }}" class="hover:text-neutral-950">{{ __('Find Your Car') }}</a>
                    </li>
                </ul>
            </nav>

            <nav aria-label="{{ __('Why Voltiva') }}">
                <p class="font-medium text-neutral-950">{{ __('Why Voltiva') }}</p>
                <ul class="mt-4 space-y-2">
                    <li>
                        <a
                            href="{{ route('pages.show', 'batteries-and-range') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Batteries & Range') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('pages.show', 'charging') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Charging') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('pages.show', 'registration') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Registration') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('pages.show', 'servicing-and-support') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Servicing & Support') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('pages.show', 'finance') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Finance') }}</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('pages.show', 'accessories') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Accessories') }}</a>
                    </li>
                </ul>
            </nav>

            <nav aria-label="{{ __('About') }}">
                <p class="font-medium text-neutral-950">{{ __('About') }}</p>
                <ul class="mt-4 space-y-2">
                    <li>
                        <a
                            href="{{ route('pages.show', 'about') }}"
                            class="hover:text-neutral-950"
                        >{{ __('About Voltiva') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('news.index') }}" class="hover:text-neutral-950">{{ __('News & Advice') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'faq') }}" class="hover:text-neutral-950">{{ __('FAQ') }}</a>
                    </li>
                    <li><a href="{{ route('contact') }}" class="hover:text-neutral-950">{{ __('Contact') }}</a></li>
                    <li>
                        <a
                            href="{{ route('enquiry') }}"
                            class="hover:text-neutral-950"
                        >{{ __('Register Your Interest') }}</a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="mt-16 flex flex-col gap-4 border-t border-neutral-300 pt-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $businessName }}</p>

            <nav aria-label="{{ __('Footer') }}" class="flex flex-wrap items-center gap-x-5 gap-y-2">
                @foreach ($footerPages() as $footerPage)
                    <a
                        href="{{ route('pages.show', $footerPage) }}"
                        class="hover:text-neutral-950"
                    >{{ $footerPage->title }}</a>
                @endforeach

                @if (Route::has('login'))
                    @auth
                        @if (auth()->user()->is_admin)
                            <a
                                href="{{ filament()->getPanel('admin')->getUrl() }}"
                                class="font-medium text-neutral-950 hover:underline"
                            >{{ __('Admin') }}</a>
                        @else
                            <a href="{{ route('dashboard') }}" class="hover:text-neutral-950">{{ __('Dashboard') }}</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="hover:text-neutral-950">{{ __('Log in') }}</a>

                        @registrationEnabled
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="hover:text-neutral-950">{{ __('Sign up') }}</a>
                            @endif
                        @endregistrationEnabled
                    @endauth
                @endif
            </nav>
        </div>
    </div>
</footer>
