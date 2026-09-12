{{--
    503 is `php artisan down`, so it renders during a deploy. The framework
    serves this one from a PRE-RENDERED file when `down --render` is used, but
    for a plain `artisan down` it renders normally -- either way the page has
    to stand on its own without the database, exactly like the 500.
--}}
<x-errors.layout
    code="503"
    :title="__('We are down for maintenance')"
    :message="__('The site is briefly offline while we update it. Nothing is wrong with your account, and this usually takes only a few minutes. Please check back shortly.')"
    icon="wrench-screwdriver"
    tint="from-teal-500 to-emerald-600"
>
    <x-slot:actions>
        {{-- full() rather than current(), so a deep link the user was on
             survives the retry with its query string intact. --}}
        <flux:button
            href="{{ url()->full() }}"
            variant="primary"
            icon="arrow-path"
        >{{ __('Check again') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
