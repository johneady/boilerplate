{{--
    Compare Cars: every figure read from the cars' own rows (see
    App\Livewire\CompareCars) -- nothing on this page is typed twice.

    The best figure in a row is marked, since "which is best at what" is the
    question a comparison exists to answer. The table scrolls sideways on a
    phone with the labels column pinned.
--}}
@php
    $vehicles = $this->vehicles;

    /**
     * The id of the car with the best value in a row, or null on a tie of all.
     */
    $best = function (callable $value, bool $highest = true) use ($vehicles): ?int {
        if ($vehicles->count() < 2) {
            return null;
        }

        $values = $vehicles->mapWithKeys(fn ($vehicle) => [$vehicle->id => (float) $value($vehicle)]);
        $target = $highest ? $values->max() : $values->min();
        $winners = $values->filter(fn ($v) => $v === $target);

        return $winners->count() === 1 ? $winners->keys()->first() : null;
    };

    $rows = [
        ['label' => __('Price'), 'value' => fn ($v) => $v->formattedPrice(), 'best' => $best(fn ($v) => $v->price_cents, false)],
        ['label' => __('Finance from'), 'value' => fn ($v) => $v->formattedMonthlyFrom() ? __(':amount a month', ['amount' => $v->formattedMonthlyFrom()]) : '—', 'best' => null],
        ['label' => __('Top speed'), 'value' => fn ($v) => __(':speed km/h', ['speed' => $v->top_speed_kmh]), 'best' => $best(fn ($v) => $v->top_speed_kmh)],
        ['label' => __('Range'), 'value' => fn ($v) => __('Up to :km km', ['km' => $v->range_km]), 'best' => $best(fn ($v) => $v->range_km)],
        ['label' => __('Battery'), 'value' => fn ($v) => $v->batteryDescription().' · '.$v->figure('battery_kwh').' kWh', 'best' => $best(fn ($v) => $v->battery_kwh)],
        ['label' => __('Charging time (230V)'), 'value' => fn ($v) => __(':hours hours', ['hours' => $v->figure('charge_hours')]), 'best' => $best(fn ($v) => $v->charge_hours, false)],
        ['label' => __('Seats'), 'value' => fn ($v) => (string) $v->seats, 'best' => $best(fn ($v) => $v->seats)],
        ['label' => __('Motor'), 'value' => fn ($v) => __(':kw kW electric motor', ['kw' => $v->figure('motor_kw')]), 'best' => $best(fn ($v) => $v->motor_kw)],
        ['label' => __('Size (L × W × H)'), 'value' => fn ($v) => $v->formattedDimensions(), 'best' => null],
        ['label' => __('Weight'), 'value' => fn ($v) => $v->kerb_weight_kg.' kg', 'best' => null],
        ['label' => __('Battery warranty'), 'value' => fn ($v) => trans_choice(':count year|:count years', $v->battery_warranty_years, ['count' => $v->battery_warranty_years]), 'best' => $best(fn ($v) => $v->battery_warranty_years)],
        ['label' => __('Licence needed'), 'value' => fn ($v) => __($v->category->licence()), 'best' => null],
    ];
@endphp

<div class="mx-auto w-full max-w-360 px-4 pt-16 pb-24 sm:px-6 lg:px-10 lg:pt-24">
    <x-voltiva.section-heading
        as="h1"
        :eyebrow="__('Compare Cars')"
        :title="__('Compare our electric cars side by side.')"
        :intro="__('Choose up to :count cars. Tap any underlined term for a plain-English explanation.', ['count' => \App\Livewire\CompareCars::MAX_SELECTED])"
    />

    {{-- The picker --}}
    <div class="mt-10 flex flex-wrap gap-2" role="group" aria-label="{{ __('Cars to compare') }}">
        @foreach ($this->allVehicles as $option)
            @php($isSelected = in_array($option->slug, $selected, true))
            <button
                type="button"
                wire:click="toggle('{{ $option->slug }}')"
                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                @class([
                    'inline-flex items-center gap-2 border px-4 py-2 text-sm transition',
                    'border-neutral-950 bg-neutral-950 text-white' => $isSelected,
                    'border-neutral-300 hover:border-neutral-950' => ! $isSelected,
                ])
            >
                @if ($isSelected)
                    <flux:icon.check variant="micro" />
                @else
                    <flux:icon.plus variant="micro" />
                @endif
                {{ $option->name }}
                <span @class(['text-xs', 'text-neutral-400' => $isSelected, 'text-neutral-500' => ! $isSelected])>{{ $option->category->label() }}</span>
            </button>
        @endforeach
    </div>

    @if ($vehicles->isEmpty())
        <p class="mt-16 text-neutral-600">{{ __('Choose at least one car above to see its details.') }}</p>
    @else
        <div class="mt-12 overflow-x-auto" wire:loading.class="opacity-60">
            <table class="w-full min-w-[44rem] table-fixed border-collapse text-left text-sm">
                <caption class="sr-only">
                    {{ __('Comparison of the selected cars') }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 w-40 bg-white sm:w-52">
                            <span class="sr-only">{{ __('Specification') }}</span>
                        </th>
                        @foreach ($vehicles as $vehicle)
                            <th scope="col" class="px-3 pb-6 align-top font-normal">
                                <a href="{{ route('cars.show', $vehicle) }}" class="group block">
                                    <x-voltiva.image
                                        :src="$vehicle->imageUrl()"
                                        :alt="$vehicle->name"
                                        ratio="aspect-4/3"
                                    />
                                    <span class="mt-4 block text-lg font-medium group-hover:underline">{{ $vehicle->name }}</span>
                                </a>
                                <span class="mt-1 block text-neutral-600"
                                    ><x-voltiva.term
                                        :key="$vehicle->category->glossaryKey()"
                                        :label="$vehicle->category->label()"
                                    />
                                    · {{ __($vehicle->category->description()) }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 border-y border-neutral-200">
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row" class="sticky left-0 z-10 bg-white py-4 pr-4 font-normal text-neutral-500">
                                {{ $row['label'] }}
                            </th>
                            @foreach ($vehicles as $vehicle)
                                <td class="px-3 py-4">
                                    <span @class(['font-medium text-volt-700' => $row['best'] === $vehicle->id])>{{ ($row['value'])($vehicle) }}</span>
                                    @if ($row['best'] === $vehicle->id)
                                        <span class="bg-volt-50 text-volt-700 ml-1 px-1.5 py-0.5 text-[11px] font-medium">{{ __('Best') }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @if ($this->equipment !== [])
                        <tr>
                            <th
                                scope="rowgroup"
                                colspan="{{ $vehicles->count() + 1 }}"
                                class="pt-10 pb-3 text-base font-medium text-neutral-950"
                            >
                                {{ __('Equipment') }}
                            </th>
                        </tr>
                        @foreach ($this->equipment as $item)
                            <tr>
                                <th
                                    scope="row"
                                    class="sticky left-0 z-10 bg-white py-3 pr-4 font-normal text-neutral-500"
                                >
                                    {{ $item }}
                                </th>
                                @foreach ($vehicles as $vehicle)
                                    <td class="px-3 py-3">
                                        @if (in_array($item, $vehicle->equipment ?? [], true))
                                            <flux:icon.check variant="mini" class="text-volt-600" />
                                            <span class="sr-only">{{ __('Included') }}</span>
                                        @else
                                            <span class="text-neutral-300" aria-hidden="true">—</span>
                                            <span class="sr-only">{{ __('Not included') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif

                    <tr>
                        <td class="sticky left-0 z-10 bg-white"></td>
                        @foreach ($vehicles as $vehicle)
                            <td class="px-3 py-8 align-top">
                                <div class="flex flex-col gap-2">
                                    <x-voltiva.button :href="route('cars.show', $vehicle)" class="w-full">
                                        {{ __('Explore') }}</x-voltiva.button>
                                    <x-voltiva.button
                                        :href="route('enquiry', ['vehicle' => $vehicle->slug, 'source' => 'compare'])"
                                        variant="secondary"
                                        class="w-full"
                                    >
                                        {{ __('Enquire') }}</x-voltiva.button>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
