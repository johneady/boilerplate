{{--
    App\Filament\Widgets\NeedsAttention: a short list of what is waiting on
    someone, each row linking to the filtered list where it is dealt with.
--}}
@php($items = $this->items())

<x-filament-widgets::widget>
    <x-filament::section :heading="__('dashboard.attention.heading')" icon="heroicon-o-bell-alert">
        @if ($items === [])
            <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400" data-test="all-clear">
                <x-filament::icon icon="heroicon-o-check-circle" class="size-5 text-success-500" />
                {{ __('dashboard.attention.all_clear') }}
            </p>
        @else
            <ul class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($items as $item)
                    <li>
                        <a
                            href="{{ $item['url'] }}"
                            class="flex items-center gap-3 py-2.5 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-400"
                        >
                            <x-filament::icon
                                :icon="$item['icon']"
                                @class([
                                    'size-5 shrink-0',
                                    'text-danger-500' => $item['color'] === 'danger',
                                    'text-warning-500' => $item['color'] === 'warning',
                                    'text-info-500' => $item['color'] === 'info',
                                ])
                            />
                            <span class="flex-1">{{ $item['label'] }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 text-gray-400" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
