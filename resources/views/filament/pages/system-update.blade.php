<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Current version') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">v{{ $currentVersion }}</p>
            </div>

            <form method="post" action="{{ $launchUrl }}">
                @csrf
                <x-filament::button type="submit">{{ __('Launch updater') }}</x-filament::button>
            </form>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Recent updates') }}</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Version') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">—</td>
                            <td class="py-2 pr-4">
                                <x-filament::badge color="primary">{{ __('Installed') }}</x-filament::badge>
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">v{{ $installation }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ __('Initial installation') }}</td>
                        </tr>
                        @foreach ($results as $result)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                    {{ $result->finishedAtDisplay() ?? '—' }}
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($result->applied())
                                        <x-filament::badge color="success">{{ __('Applied') }}</x-filament::badge>
                                    @elseif ($result->rolledBack())
                                        <x-filament::badge color="gray">{{ __('Rolled back') }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="danger">{{ __('Failed') }}</x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                    {{ $result->versionFrom ?: '—' }} → {{ $result->versionTo ?: '—' }}
                                </td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    @if ($result->error)
                                        <span class="break-all">{{ $result->failedTask ? $result->failedTask.': ' : '' }}{{ $result->error }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
