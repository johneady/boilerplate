{{--
    The counter tablet's one screen: tabs of the queue, cards of orders,
    actions sized for thumbs.

    Polls every ten seconds, which is the "automated" half of the
    requirement: a customer sends photos from the sofa by the window and the
    card appears beside the printer without anyone refreshing anything.
--}}
<div wire:poll.10s>
    <header class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-6">
        <div class="flex items-center gap-3">
            <x-app-logo-icon class="size-9" />
            <div>
                <h1 class="text-xl font-semibold text-white">{{ __('Fulfillment console') }}</h1>
                <p class="flex items-center gap-1.5 text-sm text-slate-400">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span>
                    </span>
                    {{ __('Live — new orders appear on their own') }}
                </p>
            </div>
        </div>

        <a
            href="{{ url('/admin') }}"
            class="rounded-lg px-3 py-1.5 text-sm text-slate-400 transition hover:bg-slate-800 hover:text-slate-200"
        >
            {{ __('Admin panel') }}
        </a>
    </header>

    <nav class="mx-auto flex w-full max-w-7xl gap-2 overflow-x-auto px-6 pb-6" aria-label="{{ __('Queue') }}">
        @foreach ($this->tabs as $queueTab)
            <button
                type="button"
                wire:click="$set('tab', '{{ $queueTab['value'] }}')"
                class="flex shrink-0 items-center gap-2 rounded-xl px-5 py-3 text-base font-medium transition
                    @if ($queueTab['value'] === $this->tab)
                        bg-white text-slate-900
                    @else
                        bg-slate-900 text-slate-300 hover:bg-slate-800
                    @endif"
                aria-current="{{ $queueTab['value'] === $this->tab ? 'page' : 'false' }}"
            >
                {{ __($queueTab['label']) }}
                <span
                    class="min-w-7 rounded-full px-2 py-0.5 text-center text-sm font-semibold tabular-nums
                        @if ($queueTab['value'] === $this->tab)
                            bg-slate-900 text-white
                        @else
                            bg-slate-800 text-slate-400
                        @endif"
                >{{ $queueTab['count'] }}</span>
            </button>
        @endforeach
    </nav>

    <main class="mx-auto w-full max-w-7xl px-6 pb-16">
        @if (count($this->orders) === 0)
            <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-800 py-24 text-center">
                <flux:icon name="inbox" variant="outline" class="size-12 text-slate-600" />
                <p class="mt-4 text-lg font-medium text-slate-300">{{ __('Nothing waiting') }}</p>
                <p class="mt-1 text-slate-500">{{ __('Orders sent by customers land here by themselves.') }}</p>
            </div>
        @else
            <ul class="grid gap-5 xl:grid-cols-2">
                @foreach ($this->orders as $order)
                    <li
                        class="rounded-3xl bg-slate-900 p-6 ring-1
                            @if ($order->status === App\Prints\Enums\PrintOrderStatus::Received)
                                ring-amber-500/40
                            @elseif ($order->status === App\Prints\Enums\PrintOrderStatus::Printing)
                                ring-sky-500/40
                            @else
                                ring-emerald-500/40
                            @endif"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-2xl font-bold tracking-widest text-white">
                                    {{ $order->code }}
                                </p>
                                <p class="mt-1 text-sm text-slate-400">
                                    {{ $order->customer_name }}&ensp;·&ensp;{{ $order->created_at->diffForHumans() }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($order->channel === App\Prints\Enums\PrintChannel::InStore)
                                    <span class="rounded-full bg-amber-400/10 px-3 py-1 text-sm font-medium text-amber-300">
                                        {{ __('In store') }}
                                        @if ($order->location) ·{{ $order->location->name }}@endif
                                    </span>
                                @else
                                    <span class="rounded-full bg-sky-400/10 px-3 py-1 text-sm font-medium text-sky-300">
                                        {{ __('Mail out') }}
                                    </span>
                                @endif

                                @if ($order->payment_status === App\Prints\Enums\PrintPaymentStatus::PayAtCounter)
                                    <span class="rounded-full bg-amber-400/10 px-3 py-1 text-sm font-medium text-amber-300">
                                        {{ __('Pay at counter') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-sm font-medium text-emerald-300">
                                        {{ __('Paid') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($order->channel === App\Prints\Enums\PrintChannel::Remote && filled($order->mailing_address))
                            <p class="mt-3 rounded-xl bg-slate-800/60 px-4 py-3 text-sm whitespace-pre-line text-slate-300">
                                {{ $order->mailing_address }}
                            </p>
                        @endif

                        <ul class="mt-4 grid grid-cols-4 gap-3 sm:grid-cols-5">
                            @foreach ($order->items as $item)
                                @php
                                    $thumb = $item->media?->url('thumb') ?? $item->media?->url();
                                @endphp
                                <li class="relative aspect-square overflow-hidden rounded-xl bg-slate-800">
                                    @if ($thumb)
                                        <img
                                            src="{{ $thumb }}"
                                            alt="{{ __('Photo :number', ['number' => $loop->iteration]) }}"
                                            class="size-full object-cover"
                                        />
                                    @else
                                        <div class="flex size-full items-center justify-center text-slate-600">
                                            <flux:icon name="photo" class="size-6" />
                                        </div>
                                    @endif

                                    <span class="absolute top-1 left-1 rounded-full bg-slate-950/80 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">
                                        &times;{{ $item->quantity }}
                                    </span>

                                    @if ($item->printed_at !== null)
                                        <span
                                            class="absolute right-1 bottom-1 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white"
                                            title="{{ __('Sent to :printer', ['printer' => App\Livewire\Prints\FulfillmentConsole::PRINTERS[$item->printer] ?? $item->printer]) }}"
                                        >
                                            <flux:icon name="check" class="size-4" />
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4">
                            <p class="text-sm text-slate-400">
                                {{ trans_choice(':prints print|:prints prints', $order->printCount(), ['prints' => $order->printCount()]) }}&ensp;·&ensp;
                                <span class="font-semibold text-slate-200">{{ \App\Prints\PrintPricing::money($order->prints_total_cents) }}</span>
                            </p>

                            @if ($order->status === App\Prints\Enums\PrintOrderStatus::Received)
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach (App\Livewire\Prints\FulfillmentConsole::PRINTERS as $key => $label)
                                        <button
                                            type="button"
                                            wire:click="$set('selectedPrinter.{{ $order->id }}', '{{ $key }}')"
                                            class="rounded-lg px-3 py-2 text-sm font-medium transition
                                                @if (($selectedPrinter[$order->id] ?? 'front') === $key)
                                                    bg-teal-400 text-slate-900
                                                @else
                                                    bg-slate-800 text-slate-300 hover:bg-slate-700
                                                @endif"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach

                                    <button
                                        type="button"
                                        wire:click="sendToPrinter({{ $order->id }})"
                                        wire:loading.attr="disabled"
                                        class="rounded-lg bg-teal-400 px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-teal-300 disabled:opacity-50"
                                    >
                                        {{ __('Print :prints photos', ['prints' => $order->printCount()]) }}
                                    </button>
                                </div>
                            @elseif ($order->status === App\Prints\Enums\PrintOrderStatus::Printing)
                                <button
                                    type="button"
                                    wire:click="markReady({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg bg-emerald-400 px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-emerald-300 disabled:opacity-50"
                                >
                                    {{ $order->channel === App\Prints\Enums\PrintChannel::InStore ? __('Done — ready for pickup') : __('Done — packed for the post') }}
                                </button>
                            @elseif ($order->status === App\Prints\Enums\PrintOrderStatus::Ready)
                                <button
                                    type="button"
                                    wire:click="markCompleted({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-50"
                                >
                                    {{ $order->channel === App\Prints\Enums\PrintChannel::InStore ? __('Handed over') : __('Posted') }}
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
</div>
