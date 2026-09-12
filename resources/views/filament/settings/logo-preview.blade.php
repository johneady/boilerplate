{{--
    The current logo, shown on the SEO & brand tab above its upload buttons.

    It renders x-app-logo-icon rather than the stored URL directly, so the
    preview is the same component the site chrome renders -- including the
    bundled default when nothing has been uploaded, which is the state an
    administrator most needs to see before deciding to replace it.

    The mark sits on a checkered-neutral tile because an uploaded logo may
    have a transparent background, which would otherwise be invisible against
    the panel's own surface in one of the two themes.
--}}
<div class="flex items-center gap-4">
    <div class="flex size-20 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-white p-2 dark:border-white/10 dark:bg-gray-900">
        <x-app-logo-icon class="size-full" />
    </div>

    <div class="text-sm">
        <p class="font-medium text-gray-950 dark:text-white">
            {{ $hasUploadedLogo ? __('Your logo') : __('Default logo') }}
        </p>
        <p class="text-gray-500 dark:text-gray-400">
            {{
                $hasUploadedLogo
                ? __('Shown across the site, and used for the favicon and link previews.')
                : __('No logo uploaded yet, so the bundled mark is shown.')
            }}
        </p>
    </div>
</div>
