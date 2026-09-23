{{--
    The basket and one-page checkout. Payment is simulated in this demo; see
    App\Shop\Checkout::placeOrder() for where a real payment provider slots in.
--}}
<main class="flex-1 py-12">
    <h1 class="text-4xl font-semibold tracking-tight">{{ __('Your basket') }}</h1>

    @if ($this->packages->isEmpty())
        <div class="mt-10 rounded-2xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
            <flux:icon.shopping-bag class="mx-auto size-10 text-neutral-400" />
            <flux:heading size="lg" class="mt-4">{{ __('Your basket is empty') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Browse the catalogue to find footage from locations around the world.') }}</flux:text>

            @error('basket')
                <p class="mt-4 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror

            <flux:button class="mt-6" variant="primary" :href="route('shop.index')" wire:navigate>
                {{ __('Browse packages') }}
            </flux:button>
        </div>
    @else
        <div class="mt-10 grid gap-10 lg:grid-cols-5">
            <section class="lg:col-span-3" aria-labelledby="basket-heading">
                <h2 id="basket-heading" class="sr-only">{{ __('Packages in your basket') }}</h2>

                @error('basket')
                    <div
                        class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200"
                        role="alert"
                    >
                        {{ $message }}
                    </div>
                @enderror

                <ul class="divide-y divide-neutral-200 rounded-2xl border border-neutral-200 bg-white dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900">
                    @foreach ($this->packages as $package)
                        <li wire:key="basket-{{ $package->id }}" class="flex gap-4 p-4">
                            <a
                                href="{{ route('shop.show', $package) }}"
                                wire:navigate
                                class="w-28 shrink-0 overflow-hidden rounded-lg bg-neutral-100 sm:w-36 dark:bg-neutral-800"
                            >
                                @if ($package->imageUrl() !== null)
                                    <img
                                        src="{{ $package->imageUrl() }}"
                                        alt=""
                                        class="aspect-16/10 size-full object-cover"
                                    />
                                @endif
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <a
                                            href="{{ route('shop.show', $package) }}"
                                            wire:navigate
                                            class="font-semibold hover:underline"
                                        >{{ $package->title }}</a>
                                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                                            {{ $package->location }} · {{ $package->resolution }} · {{ trans_choice(':count clip|:count clips', $package->clip_count, ['count' => $package->clip_count]) }}
                                        </p>
                                    </div>
                                    <p class="font-semibold">{{ $package->formattedPrice() }}</p>
                                </div>

                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <x-shop.availability :package="$package" />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="remove({{ $package->id }})"
                                        :aria-label="__('Remove :package', ['package' => $package->title])"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <flux:button class="mt-6" variant="ghost" icon="arrow-left" :href="route('shop.index')" wire:navigate>
                    {{ __('Continue browsing') }}
                </flux:button>
            </section>

            <section class="lg:col-span-2" aria-labelledby="checkout-heading">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 id="checkout-heading" class="text-xl font-semibold tracking-tight">{{ __('Checkout') }}</h2>

                    <dl class="mt-6 space-y-2 text-sm">
                        <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
                            <dt>
                                {{ trans_choice(':count package|:count packages', $this->packages->count(), ['count' => $this->packages->count()]) }}
                            </dt>
                            <dd>{{ $this->total }}</dd>
                        </div>
                        <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
                            <dt>{{ __('Delivery') }}</dt>
                            <dd>{{ __('Instant download') }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-neutral-200 pt-3 text-base font-semibold dark:border-neutral-800">
                            <dt>{{ __('Total') }}</dt>
                            <dd>{{ $this->total }}</dd>
                        </div>
                    </dl>

                    <form wire:submit="placeOrder" class="mt-6 space-y-5">
                        <flux:input wire:model="name" :label="__('Full name')" autocomplete="name" required />

                        <flux:input
                            wire:model="email"
                            type="email"
                            :label="__('Email address')"
                            :description="__('Your download links and licence certificate are sent here.')"
                            autocomplete="email"
                            required
                        />

                        <flux:field variant="inline">
                            <flux:checkbox wire:model="acceptTerms" />
                            <flux:label>{{ __('I accept the royalty-free licence terms') }}</flux:label>
                            <flux:error name="acceptTerms" />
                        </flux:field>

                        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                            <p class="flex items-center gap-2 font-medium">
                                <flux:icon.credit-card variant="micro" /> {{ __('Demo checkout') }}
                            </p>
                            <p class="mt-1">
                                {{ __('No payment is taken in this demo. In production this step hands off to Stripe Checkout (cards, Apple Pay and Google Pay) before the order is confirmed.') }}
                            </p>
                        </div>

                        <flux:button type="submit" variant="primary" class="w-full" icon="lock-closed">
                            <span
                                wire:loading.remove
                                wire:target="placeOrder"
                            >{{ __('Place order — :total', ['total' => $this->total]) }}</span>
                            <span wire:loading wire:target="placeOrder">{{ __('Placing your order...') }}</span>
                        </flux:button>
                    </form>
                </div>
            </section>
        </div>
    @endif
</main>
