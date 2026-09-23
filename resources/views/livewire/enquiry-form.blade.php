{{--
    The enquiry form: the fields the brief lists (name, email, phone, car,
    location, driving requirements, finance and registration interest,
    message). Embedded in several places -- see App\Livewire\EnquiryForm.
--}}
<div>
    @if ($sent)
        <div class="border-volt-600 bg-volt-50 border-l-2 px-5 py-4" data-test="enquiry-sent" role="status">
            <p class="font-medium">{{ __('Thank you – we have your enquiry.') }}</p>
            <p class="mt-1 text-sm text-neutral-700">
                {{ __('A member of our team will contact you within one working day. We have also sent you a confirmation email.') }}
            </p>
            <button
                type="button"
                wire:click="$set('sent', false)"
                class="mt-3 text-sm font-medium underline underline-offset-2"
            >
                {{ __('Send another enquiry') }}
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
                <flux:input
                    wire:model="phone"
                    :label="__('Phone')"
                    type="tel"
                    autocomplete="tel"
                    :placeholder="__('Optional – the quickest way to reach you')"
                />
                <flux:input
                    wire:model="location"
                    :label="__('Where in Mallorca?')"
                    type="text"
                    autocomplete="address-level2"
                    :placeholder="__('e.g. Palma, Sóller, Alcúdia')"
                />
            </div>

            <flux:select wire:model="vehicleId" :label="__('Which car are you interested in?')">
                <flux:select.option value="">{{ __('Not sure yet') }}</flux:select.option>
                @foreach ($this->vehicles as $vehicleOption)
                    <flux:select.option :value="(string) $vehicleOption->id">
                        {{ $vehicleOption->name }} ({{ $vehicleOption->category->label() }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox.group wire:model="drivingNeeds" :label="__('How will you use it?')">
                <div class="grid gap-3 pt-1 sm:grid-cols-2">
                    @foreach ($drivingNeedOptions as $need)
                        <flux:checkbox :value="$need->value" :label="__($need->label())" />
                    @endforeach
                </div>
            </flux:checkbox.group>

            <div class="space-y-3">
                <flux:checkbox wire:model="financeInterest" :label="__('I would like a finance quote')" />
                <flux:checkbox wire:model="registrationInterest" :label="__('Please handle the registration for me')" />
            </div>

            <flux:textarea
                wire:model="message"
                :label="__('Message')"
                rows="4"
                :placeholder="__('Anything else we should know?')"
            />

            {{-- The honeypot: hidden from people and from assistive technology. See App\Livewire\Contact. --}}
            <div hidden aria-hidden="true">
                <label for="enquiry-website-{{ $this->getId() }}">{{ __('Leave this field empty') }}</label>
                <input
                    id="enquiry-website-{{ $this->getId() }}"
                    type="text"
                    wire:model="website"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <div class="flex flex-wrap items-center gap-4 pt-2">
                <x-voltiva.button type="submit" class="min-w-48" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">{{ __('Send enquiry') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </x-voltiva.button>
                <p class="text-xs text-neutral-500">
                    {{ __('We use your details only to answer your enquiry.') }}
                    <a
                        href="{{ route('pages.show', 'privacy') }}"
                        class="underline underline-offset-2"
                    >{{ __('Privacy Policy') }}</a>
                </p>
            </div>
        </form>
    @endif
</div>
