{{--
    The read-only production configuration report on the Diagnostics tab.

    Everything here comes from the environment rather than the settings table,
    so it is deliberately presented as findings rather than fields: there is
    nothing to submit, and the fix is always a change to the deployment's
    environment followed by a restart.

    Failures are listed first and passing checks are collapsed behind a
    <details>, because the reason to open this tab is to find what is wrong --
    a full-length list of green rows would bury a single red one.
--}}
@php
    use App\Settings\DiagnosticSeverity;

    $failures = collect($results)->reject(fn ($result) => $result->passed);
    $passed = collect($results)->filter(fn ($result) => $result->passed);

    $errorCount = $failures->where('severity', DiagnosticSeverity::Error)->count();
    $warningCount = $failures->where('severity', DiagnosticSeverity::Warning)->count();
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <p class="font-medium text-gray-950 dark:text-white">{{ __('Configuration audit') }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ __('Checked against the configuration this application is running with right now.') }}
            </p>
        </div>

        <x-filament::badge :color="$environment === 'production' ? 'success' : 'gray'">
            {{ __('Environment: :name', ['name' => $environment]) }}
        </x-filament::badge>
    </div>

    @if ($failures->isEmpty())
        <x-filament::callout
            color="success"
            icon="heroicon-o-check-circle"
            :heading="__('No issues found')"
            :description="__('Every check passed for this environment.')"
        />
    @else
        <div class="flex flex-wrap gap-2">
            @if ($errorCount > 0)
                <x-filament::badge color="danger" icon="heroicon-o-x-circle">
                    {{ trans_choice('{1} :count error|[2,*] :count errors', $errorCount, ['count' => $errorCount]) }}
                </x-filament::badge>
            @endif

            @if ($warningCount > 0)
                <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
                    {{ trans_choice('{1} :count warning|[2,*] :count warnings', $warningCount, ['count' => $warningCount]) }}
                </x-filament::badge>
            @endif
        </div>

        <div class="space-y-3">
            @foreach ($failures as $result)
                {{-- The callout renders its `description` prop and ignores
                     the default slot, so the detail must be passed as an
                     attribute or it is silently dropped. --}}
                <x-filament::callout
                    :color="$result->severity->color()"
                    :icon="$result->severity === DiagnosticSeverity::Error ? 'heroicon-o-x-circle' : 'heroicon-o-exclamation-triangle'"
                    :heading="$result->name"
                    :description="$result->detail"
                />
            @endforeach
        </div>
    @endif

    @if ($passed->isNotEmpty())
        <details class="group">
            <summary class="cursor-pointer text-sm font-medium text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white">
                {{ trans_choice('{1} :count passing check|[2,*] :count passing checks', $passed->count(), ['count' => $passed->count()]) }}
            </summary>

            <ul class="mt-3 space-y-2">
                @foreach ($passed as $result)
                    <li class="flex items-start gap-2 text-sm">
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="text-success-600 dark:text-success-400 mt-0.5 size-4 shrink-0"
                        />

                        <span>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $result->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400">&mdash; {{ $result->detail }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
