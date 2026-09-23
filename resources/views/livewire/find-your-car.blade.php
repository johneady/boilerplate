{{--
    Find Your Car: four questions on the left, the range re-sorted with its
    reasons on the right, updating as each answer is given. See
    App\Livewire\FindYourCar for how a car is judged.
--}}
@php
    $questions = [
        ['model' => 'licence', 'legend' => __('Which driving licence do you have?'), 'options' => \App\Livewire\FindYourCar::LICENCES],
        ['model' => 'roads', 'legend' => __('Where will you drive?'), 'options' => \App\Livewire\FindYourCar::ROADS],
        ['model' => 'distance', 'legend' => __('How far do you drive on a typical day?'), 'options' => \App\Livewire\FindYourCar::DISTANCES],
        ['model' => 'budget', 'legend' => __('What is your budget?'), 'options' => \App\Livewire\FindYourCar::BUDGETS],
    ];
    $verdicts = [
        'match' => ['label' => __('Great match'), 'class' => 'bg-volt-700 text-white'],
        'consider' => ['label' => __('Worth a look'), 'class' => 'bg-neutral-200 text-neutral-900'],
        'unsuitable' => ['label' => __('Not for you'), 'class' => 'bg-white text-neutral-500 border border-neutral-300'],
    ];
@endphp

<div class="mx-auto w-full max-w-360 px-4 pt-16 pb-24 sm:px-6 lg:px-10 lg:pt-24">
    <x-voltiva.section-heading
        as="h1"
        :eyebrow="__('Find Your Car')"
        :title="__('Which Voltiva suits the way you drive?')"
        :intro="__('Four quick questions. Your matches update as you answer.')"
    />

    <div class="mt-14 grid gap-14 lg:grid-cols-[24rem_1fr] lg:gap-20">
        <div class="space-y-10">
            @foreach ($questions as $number => $question)
                <fieldset>
                    <legend class="flex gap-3 font-medium">
                        <span class="text-volt-700 text-sm">0{{ $number + 1 }}</span> {{ $question['legend'] }}
                    </legend>
                    <div class="mt-4 grid gap-2">
                        @foreach ($question['options'] as $value => $label)
                            <label @class([
                                'flex cursor-pointer items-center justify-between gap-3 border px-4 py-3 text-sm transition has-focus-visible:outline-2 has-focus-visible:outline-volt-600',
                                'border-neutral-950 bg-neutral-950 text-white' => $this->{$question['model']} === (string) $value,
                                'border-neutral-300 hover:border-neutral-950' => $this->{$question['model']} !== (string) $value,
                            ])>
                                <input
                                    type="radio"
                                    wire:model.live="{{ $question['model'] }}"
                                    value="{{ $value }}"
                                    class="sr-only"
                                />
                                {{ __($label) }}
                                @if ($this->{$question['model']} === (string) $value)
                                    <flux:icon.check variant="micro" />
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @if ($question['model'] === 'licence')
                        <p class="mt-3 text-xs leading-relaxed text-neutral-500">
                            <x-voltiva.term key="l6e" :label="__('L6e cars')" />
                            {{ __('need an AM licence, from age 15.') }}
                            <x-voltiva.term key="l7e" :label="__('L7e cars')" /> {{ __('need a B1 or car licence.') }}
                        </p>
                    @endif
                </fieldset>
            @endforeach

            @if ($this->answered > 0)
                <button type="button" wire:click="startOver" class="text-sm font-medium underline underline-offset-2">
                    {{ __('Start over') }}
                </button>
            @endif
        </div>

        <div aria-live="polite">
            <p class="text-sm text-neutral-500">
                @if ($this->answered === 0)
                    {{ __('Showing the whole range. Answer the questions to narrow it down.') }}
                @else
                    {{ trans_choice(':count of 4 questions answered|:count of 4 questions answered', $this->answered, ['count' => $this->answered]) }}
                @endif
            </p>

            @if ($this->answered > 0 && collect($this->results)->every(fn (array $result): bool => $result['verdict'] === 'unsuitable'))
                <div
                    class="border-volt-600 bg-volt-50 mt-6 border-l-2 px-5 py-4 text-sm leading-relaxed"
                    data-test="finder-no-match"
                >
                    <p class="font-medium">{{ __('None of our cars fits every answer yet.') }}</p>
                    <p class="mt-1 text-neutral-700">
                        {{ __('Cars you can drive with an AM licence are limited to 45 km/h, so they are not suited to main roads. Talk to us – we can explain your licence options.') }}
                    </p>
                    <a
                        href="{{ route('enquiry', ['source' => 'finder']) }}"
                        class="mt-2 inline-block font-medium underline underline-offset-2"
                    >{{ __('Ask our team') }}</a>
                </div>
            @endif

            <ul class="mt-6 space-y-6" wire:loading.class="opacity-60">
                @foreach ($this->results as $result)
                    @php($vehicle = $result['vehicle'])
                    <li @class(['grid gap-6 border p-5 sm:grid-cols-[14rem_1fr] sm:p-6', 'border-neutral-950' => $result['verdict'] === 'match', 'border-neutral-200' => $result['verdict'] !== 'match', 'opacity-60' => $result['verdict'] === 'unsuitable'])>
                        <x-voltiva.image :src="$vehicle->imageUrl()" :alt="$vehicle->name" ratio="aspect-4/3" />
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($this->answered > 0)
                                    <span class="px-2 py-1 text-[11px] font-medium tracking-widest uppercase {{ $verdicts[$result['verdict']]['class'] }}">{{ $verdicts[$result['verdict']]['label'] }}</span>
                                @endif
                                <span class="text-xs tracking-widest text-neutral-500 uppercase">{{ $vehicle->category->label() }}</span>
                            </div>
                            <h2 class="mt-3 text-2xl font-medium tracking-tight">{{ $vehicle->name }}</h2>
                            <p class="mt-1 text-neutral-600">{{ $vehicle->tagline }}</p>

                            @if ($result['reasons'] !== [])
                                <ul class="mt-4 space-y-1.5 text-sm">
                                    @foreach ($result['reasons'] as $reason)
                                        <li class="flex gap-2">
                                            @if ($reason['ok'])
                                                <flux:icon.check
                                                    variant="micro"
                                                    class="text-volt-600 mt-0.5 shrink-0"
                                                />
                                            @else
                                                <flux:icon.x-mark
                                                    variant="micro"
                                                    class="mt-0.5 shrink-0 text-neutral-400"
                                                />
                                            @endif
                                            {{ $reason['text'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                                <span class="font-medium">{{ __('From :price', ['price' => $vehicle->formattedPrice()]) }}</span>
                                <a
                                    href="{{ route('cars.show', $vehicle) }}"
                                    class="hover:text-volt-700 font-medium underline underline-offset-2"
                                >{{ __('Explore') }}</a>
                                @if ($result['verdict'] !== 'unsuitable')
                                    <a
                                        href="{{ route('enquiry', ['vehicle' => $vehicle->slug, 'source' => 'finder']) }}"
                                        class="hover:text-volt-700 font-medium underline underline-offset-2"
                                    >{{ __('Enquire about this car') }}</a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
