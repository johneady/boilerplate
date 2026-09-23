@props([
    'current',
])

{{--
    The segmented control at the top of each calculator, so a visitor can hop
    between the two without going back to the landing page. It is the phone's
    only way across: the header hides its calculator links below sm.

    Plain links (no wire:navigate), for the same reason as the header's: the
    calculators' script binds on a full page load.
--}}
<nav
    aria-label="{{ __('Calculators') }}"
    {{ $attributes->class('grid grid-cols-2 gap-1 rounded-xl border border-neutral-200 bg-neutral-100/80 p-1 text-sm font-medium dark:border-neutral-800 dark:bg-neutral-900/80') }}
>
    @foreach ([
        'calculators.income-planner' => __('New agents'),
        'calculators.split-comparison' => __('Active agents'),
    ] as $routeName => $label)
        <a
            href="{{ route($routeName) }}"
            @if ($current === $routeName) aria-current="page" @endif
            @class([
                'rounded-lg px-3 py-2 text-center transition',
                'bg-white text-neutral-900 shadow-sm dark:bg-neutral-800 dark:text-white' => $current === $routeName,
                'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white' => $current !== $routeName,
            ])
        >
            {{ $label }}
        </a>
    @endforeach
</nav>
