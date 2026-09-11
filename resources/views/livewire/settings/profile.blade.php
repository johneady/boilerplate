<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <div class="my-6 flex items-center gap-4">
            <flux:avatar
                circle
                size="xl"
                :src="$this->avatarUrl"
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />

            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <flux:button size="sm" x-on:click="$refs.avatarInput.click()">
                        {{ __('Change photo') }}
                    </flux:button>

                    @if ($this->avatarUrl)
                        <flux:button
                            size="sm"
                            variant="subtle"
                            wire:click="deleteAvatar"
                            wire:confirm="{{ __('Remove your profile photo?') }}"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    @endif
                </div>

                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('JPG, PNG or WebP. Up to :size MB.', ['size' => floor(config('images.max_kilobytes') / 1024)]) }}
                </flux:text>

                <div wire:loading wire:target="avatar">
                    <flux:text size="sm">{{ __('Uploading...') }}</flux:text>
                </div>

                <flux:error name="avatar" />
            </div>

            {{--
                The file input is hidden and driven by the button above; a bare
                file input cannot be styled to match Flux.

                updateAvatar is called from Livewire's livewire-upload-finish
                hook rather than the input's own change event: wire:model starts
                uploading the moment a file is picked, so a change handler would
                fire while that upload is still in flight and find $avatar not
                yet set.
            --}}
            <input
                type="file"
                x-ref="avatarInput"
                wire:model="avatar"
                accept="{{ collect(config('images.accepted_extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                class="hidden"
                data-test="avatar-input"
                x-on:livewire-upload-finish="$wire.updateAvatar()"
            />
        </div>

        <flux:separator variant="subtle" />

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link
                                class="cursor-pointer text-sm"
                                wire:click.prevent="resendVerificationNotification"
                            >
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
