{{--
    The shell for the counter's tablet: the fulfillment console only.

    A kiosk screen, not a page -- dark for a shop floor, nothing to tap that
    leaves the tool, and no marketing chrome between a staff member and the
    queue. The one affordance besides the console itself is the way back to
    the panel, kept small in the corner for whoever manages the tablet.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => __('Fulfillment console')])
</head>
<body class="antialiased">
    <div class="min-h-dvh bg-slate-950 text-slate-100">{{ $slot }}</div>
    @fluxScripts
</body>
</html>
