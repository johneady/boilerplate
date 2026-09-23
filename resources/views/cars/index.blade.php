{{--
    The car range: all cars, or one EU class. $category is null for the full
    range, otherwise a VehicleCategory, whose own copy introduces the page --
    the class explains the licence and speed, so no editor writes it twice.
--}}
@php
    $title = $category === null ? __('Electric Cars') : __(':class Electric Cars', ['class' => $category->label()]);
    $tabs = [
        ['href' => route('cars.index'), 'label' => __('All cars'), 'active' => $category === null],
        ['href' => route('cars.category', 'l6e'), 'label' => 'L6e', 'active' => $category?->value === 'l6e'],
        ['href' => route('cars.category', 'l7e'), 'label' => 'L7e', 'active' => $category?->value === 'l7e'],
    ];
@endphp

<x-layouts::public
    :title="$title"
    :description="$category === null
    ? __('Compact electric cars for everyday life in Mallorca: L6e and L7e models with prices, range and charging explained.')
    : __($category->audience())"
>
    <section class="mx-auto w-full max-w-360 px-4 pt-16 pb-24 sm:px-6 lg:px-10 lg:pt-24">
        <p class="text-volt-700 text-xs font-medium tracking-[0.2em] uppercase">{{ __('Cars') }}</p>
        <h1 class="mt-3 text-4xl font-medium tracking-tight sm:text-5xl">{{ $title }}</h1>

        <div class="mt-5 max-w-2xl text-lg leading-relaxed text-neutral-600">
            @if ($category === null)
                <p>
                    {{ __('Two kinds of compact electric car, both made for Mallorca. Not sure which you need? Answer four questions and we will suggest one.') }}
                </p>
            @else
                <p>
                    <x-voltiva.term :key="$category->glossaryKey()" :label="$category->label()" />
                    · {{ __($category->description()) }}
                </p>
                <p class="mt-2">
                    {{ __($category->audience()) }} {{ __('Licence: :licence.', ['licence' => __($category->licence())]) }}
                </p>
            @endif
        </div>

        <div class="mt-10 flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200">
            <nav aria-label="{{ __('Car classes') }}" class="-mb-px flex gap-6">
                @foreach ($tabs as $tab)
                    <a
                        href="{{ $tab['href'] }}"
                        @class([
                            'border-b-2 pb-3 text-sm font-medium transition',
                            'border-neutral-950 text-neutral-950' => $tab['active'],
                            'border-transparent text-neutral-500 hover:text-neutral-950' => ! $tab['active'],
                        ])
                        @if ($tab['active']) aria-current="page" @endif
                    >{{ $tab['label'] }}</a>
                @endforeach
            </nav>
            <div class="flex gap-5 pb-3 text-sm">
                <a href="{{ route('compare') }}" class="hover:text-volt-700 font-medium">{{ __('Compare cars') }}</a>
                <a href="{{ route('finder') }}" class="hover:text-volt-700 font-medium">{{ __('Find your car') }}</a>
            </div>
        </div>

        @if ($vehicles->isEmpty())
            <p class="mt-16 text-neutral-600">
                {{ __('New cars are on their way. Register your interest and we will let you know first.') }}
            </p>
        @else
            <div class="mt-12 grid gap-x-8 gap-y-16 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($vehicles as $vehicle)
                    <x-voltiva.vehicle-card :vehicle="$vehicle" />
                @endforeach
            </div>
        @endif
    </section>

    <x-voltiva.enquiry-section source="range" :title="__('Not sure which car is right for you?')" />
</x-layouts::public>
