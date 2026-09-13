{{--
    The panel's brand lockup: the mark before the business name, in the
    sidebar header (the panel's top left corner) and the mobile topbar.

    Filament renders a brand logo IN PLACE OF the plain brand name, so this
    view draws both halves of the lockup itself. x-app-logo-icon resolves the
    Logo setting's mark conversion, or the bundled SVG when none is stored,
    and $businessName comes from the View::composer('*') that feeds every
    view -- both at render time, so an upload or a rename shows on the next
    page load without reconfiguring the panel.

    The fi-logo wrapper this renders inside is a fixed-height flex row that
    already carries the font styling, so the lockup only fills that height
    and keeps the mark beside the name.
--}}
<span class="flex h-full items-center gap-2">
    <x-app-logo-icon class="h-full" />
    {{ $businessName }}
</span>
