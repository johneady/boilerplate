{{--
    The user menu matches the blue sidebar it sits in. Flux hardcodes zinc for
    the menu surface, item hover and separator, so each is overridden here; the
    avatar and heading follow the accent tokens in resources/css/app.css.
--}}
<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        class="hover:bg-blue-500/10! dark:hover:bg-blue-400/10!"
        data-test="sidebar-menu-button"
    />

    <flux:menu class="border-blue-100! bg-blue-50/95! dark:border-blue-800! dark:bg-blue-900!">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        {{-- flux:menu.separator would put this on its wrapper, not the line. --}}
        <div class="-mx-[.3125rem] my-[.3125rem] h-px">
            <flux:separator class="bg-blue-200! dark:bg-blue-700!" />
        </div>
        <flux:menu.radio.group>
            <flux:menu.item
                :href="route('profile.edit')"
                icon="cog"
                class="data-active:bg-blue-500/10! data-active:text-blue-700! dark:data-active:bg-blue-400/20! dark:data-active:text-blue-100!"
                wire:navigate
            >
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer data-active:bg-blue-500/10! data-active:text-blue-700! dark:data-active:bg-blue-400/20! dark:data-active:text-blue-100!"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
