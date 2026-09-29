<div class="flex items-center gap-3">
    <flux:button
        wire:click="toggle"
        :variant="$following ? 'filled' : 'primary'"
        :icon="$following ? 'check' : 'heart'"
        data-test="follow-button"
    >
        {{ $following ? __('Following') : __('Follow') }}
    </flux:button>

    <span class="text-sm text-neutral-600 dark:text-neutral-400" data-test="follower-count">
        {{ trans_choice(':count follower|:count followers', $followers, ['count' => number_format($followers)]) }}
    </span>
</div>
