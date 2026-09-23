@props([
    'vehicle',
])

{{--
    A car in a grid: fixed-shape photo, name, class and price, then the three
    numbers a customer asks first. The same card on the home page, the range
    listings and "other cars" -- one design, every car.
--}}
<article {{ $attributes->class('group relative flex flex-col') }}>
    <x-voltiva.image
        :src="$vehicle->imageUrl()"
        :alt="$vehicle->name"
        ratio="aspect-4/3"
        class="transition duration-500"
    >
        <span class="absolute top-4 left-4 bg-white px-2 py-1 text-[11px] font-medium tracking-widest uppercase">{{ $vehicle->category->label() }}</span>
    </x-voltiva.image>

    <div class="mt-5 flex items-start justify-between gap-4">
        <div>
            <h3 class="text-xl font-medium tracking-tight">
                <a
                    href="{{ route('cars.show', $vehicle) }}"
                    class="after:absolute after:inset-0"
                >{{ $vehicle->name }}</a>
            </h3>
            <p class="mt-1 text-sm text-neutral-600">{{ $vehicle->tagline }}</p>
        </div>
        <p class="shrink-0 text-right text-sm">
            <span class="block text-neutral-500">{{ __('From') }}</span>
            <span class="font-medium">{{ $vehicle->formattedPrice() }}</span>
        </p>
    </div>

    <dl class="mt-5 grid grid-cols-3 border-t border-neutral-200 pt-4 text-sm">
        <div>
            <dt class="text-neutral-500">{{ __('Range') }}</dt>
            <dd class="mt-0.5 font-medium">{{ __(':km km', ['km' => $vehicle->range_km]) }}</dd>
        </div>
        <div>
            <dt class="text-neutral-500">{{ __('Top speed') }}</dt>
            <dd class="mt-0.5 font-medium">{{ __(':speed km/h', ['speed' => $vehicle->top_speed_kmh]) }}</dd>
        </div>
        <div>
            <dt class="text-neutral-500">{{ __('Seats') }}</dt>
            <dd class="mt-0.5 font-medium">{{ $vehicle->seats }}</dd>
        </div>
    </dl>

    <span class="group-hover:text-volt-700 mt-5 inline-flex items-center gap-1 text-sm font-medium">
        {{ __('Explore :name', ['name' => $vehicle->name]) }}
        <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
    </span>
</article>
