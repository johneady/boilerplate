{{--
    The "coming soon" holding page.

    Served in place of the public site by App\Http\Middleware\ShowComingSoonPage
    while Settings -> Launch -> "Show the coming soon page" is on, and always
    reachable at /coming-soon so the owner can see it before switching it on.

    Deliberately self-contained: its own <head>, and its styles and the little
    oven script inline rather than in the Vite bundle. It is the page a new
    domain shows while the real site is still being built, so it must not
    depend on the build that is being worked on behind it -- and it can be
    saved as a static file and dropped on any host as it stands.

    $message is the owner's optional line from the Launch settings; null on the
    preview route, which falls back to the default copy.
--}}
@php
    $settings = app(\App\Settings\Settings::class);
    $businessEmail = $settings->string(\App\Settings\SettingKey::BusinessEmail);
    $businessPhone = $settings->string(\App\Settings\SettingKey::BusinessPhone);
    $faviconUrl = $settings->logoUrl('favicon');

    // The oven's patter, rotated by the script at the foot of the page.
    $quips = [
        __('Preheating the internet to 350°F…'),
        __('Proofing the pixels. They need about an hour.'),
        __('Folding butter into the menu. Lots of butter.'),
        __('Letting the order form rise somewhere warm.'),
        __('Brushing the buttons with egg wash for extra shine.'),
        __('Taste-testing every page. For quality control.'),
        __('Scraping a slightly burnt footer off the tray.'),
    ];
    $displays = ['350°F', __('PROOFING'), __('RISING'), __('NO PEEKING'), '350°F', __('ALMOST…')];
    $themeQuips = [
        'night' => __('The night shift has clocked in. Croissants rise best under the moon.'),
        'day' => __('Good morning! The buns are up, so we are too.'),
    ];
    $clicks = [
        __('Hey! Every time you open the door it takes five minutes longer.'),
        __('Turning it up to 450°F. Just kidding, we like it golden, not black.'),
        __('The timer says “soon”. The timer always says “soon”.'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __(':business — Coming soon', ['business' => $businessName]) }}</title>
    {{-- A holding page is never worth indexing: it would be the snippet a
         search engine shows long after the real site is up. --}}
    <meta name="robots" content="noindex, nofollow" />
    <meta
        name="description"
        content="{{ __('Our website is in the oven. Fresh bakes, a full menu and online ordering are coming soon.') }}"
    />
    <meta property="og:title" content="{{ __(':business — Coming soon', ['business' => $businessName]) }}" />
    <meta
        property="og:description"
        content="{{ __('Our website is in the oven. Fresh bakes, a full menu and online ordering are coming soon.') }}"
    />
    <meta property="og:image" content="{{ asset('images/bakery/oven-backdrop.webp') }}" />
    <link rel="icon" href="/favicon.ico" sizes="any" />
    @if ($faviconUrl !== null)
        <link rel="icon" href="{{ $faviconUrl }}" sizes="any" />
    @else
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link
        href="https://fonts.bunny.net/css?family=fraunces:600,700,600i|instrument-sans:400,500,600"
        rel="stylesheet"
    />

    {{-- Applied before the page paints, so a returning night-shift visitor
         never sees a flash of the light page. Light unless they chose dark. --}}
    <script>
        try {
            if (localStorage.getItem('coming-soon-theme') === 'dark') {
                document.documentElement.dataset.theme = 'dark';
            }
        } catch (e) {}
    </script>

    <style>
        :root {
            --crust: #2b1a10;
            --crust-deep: #170d07;
            --cream: #fbf3e6;
            --cream-dim: #e9dcc6;
            --butter: #f6c96b;
            --ember: #ff8a3d;
            --ember-deep: #d9481c;
            --enamel: #f3e6d3;
            --enamel-shade: #d8c3a5;
            --steel: #3a2a20;
            --leaf: #9ccf7a;

            /* Morning bake: the default. */
            --bg: #fbf4e9;
            --text: #2b1a10;
            --text-dim: #6b4f3a;
            --text-faint: rgba(43, 26, 16, .45);
            --accent: #b0581a;
            --accent-soft: rgba(234, 138, 58, .12);
            --accent-line: rgba(176, 88, 26, .35);
            --surface: rgba(255, 251, 244, .8);
            --surface-soft: rgba(255, 255, 255, .6);
            --line: rgba(43, 26, 16, .12);
            --line-strong: rgba(43, 26, 16, .22);
            --track: rgba(43, 26, 16, .1);
            --steam: rgba(120, 90, 70, .45);
            --backdrop-opacity: .24;
            --backdrop-filter: sepia(.35) saturate(.95) blur(1px);
            --glow:
                radial-gradient(60rem 40rem at 50% 62%, rgba(255, 160, 80, .22), transparent 60%),
                linear-gradient(180deg, rgba(251, 244, 233, .5), rgba(251, 244, 233, .92) 80%);
            color-scheme: light;
        }

        /* The night shift. */
        :root[data-theme='dark'] {
            --bg: var(--crust-deep);
            --text: var(--cream);
            --text-dim: var(--cream-dim);
            --text-faint: rgba(251, 243, 230, .5);
            --accent: var(--butter);
            --accent-soft: rgba(246, 201, 107, .08);
            --accent-line: rgba(246, 201, 107, .35);
            --surface: rgba(23, 13, 7, .55);
            --surface-soft: rgba(251, 243, 230, .06);
            --line: rgba(251, 243, 230, .12);
            --line-strong: rgba(251, 243, 230, .22);
            --track: rgba(251, 243, 230, .1);
            --steam: rgba(251, 243, 230, .55);
            --backdrop-opacity: .38;
            --backdrop-filter: sepia(.45) saturate(1.1) blur(1px);
            --glow:
                radial-gradient(60rem 40rem at 50% 62%, rgba(255, 138, 61, .20), transparent 60%),
                linear-gradient(180deg, rgba(23, 13, 7, .45), rgba(23, 13, 7, .88) 85%);
            color-scheme: dark;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { margin: 0; min-height: 100%; }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            color: var(--text);
            background: var(--bg);
            overflow-x: hidden;
            transition: background-color .6s ease, color .6s ease;
            -webkit-font-smoothing: antialiased;
        }

        /* The real bakery, faded right back so it reads as warmth, not detail. */
        .backdrop {
            position: fixed;
            inset: 0;
            background: url('{{ asset('images/bakery/oven-backdrop.webp') }}') center / cover no-repeat;
            opacity: var(--backdrop-opacity);
            filter: var(--backdrop-filter);
            transition: opacity .6s ease;
            transform: scale(1.06);
            z-index: 0;
        }

        .glow {
            position: fixed;
            inset: 0;
            background: var(--glow);
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            max-width: 72rem;
            margin: 0 auto;
            padding: 1.5rem 1.25rem 2rem;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-weight: 600;
            letter-spacing: .01em;
        }

        .brand svg, .brand img { width: 2rem; height: 2rem; }

        /* Headline, then the oven, then the bake sheet on a phone; the oven
           beside both on a wide screen. */
        main {
            flex: 1;
            display: grid;
            grid-template-areas: 'intro' 'oven' 'sheet';
            gap: 2.5rem;
            align-content: center;
            padding: 2.5rem 0;
        }

        .intro { grid-area: intro; }
        .sheet { grid-area: sheet; }
        .oven-wrap { grid-area: oven; }

        @media (min-width: 960px) {
            main {
                grid-template-areas: 'intro oven' 'sheet oven';
                grid-template-columns: 1.05fr 1fr;
                column-gap: 4rem;
                row-gap: 2rem;
            }

            .sheet { align-self: start; }
            .intro { align-self: end; }
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .8rem;
            border: 1px solid var(--accent-line);
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .eyebrow .dot {
            width: .5rem;
            height: .5rem;
            border-radius: 50%;
            background: var(--ember);
            box-shadow: 0 0 0 0 rgba(255, 138, 61, .7);
            animation: ping 1.8s infinite;
        }

        h1 {
            margin: 1.25rem 0 0;
            font-family: 'Fraunces', ui-serif, Georgia, serif;
            font-weight: 700;
            font-size: clamp(2.6rem, 6vw, 4.6rem);
            line-height: 1.02;
            letter-spacing: -.02em;
            text-wrap: balance;
        }

        h1 em {
            font-style: italic;
            font-weight: 600;
            color: var(--accent);
        }

        .lede {
            margin: 1.25rem 0 0;
            max-width: 34rem;
            font-size: 1.125rem;
            line-height: 1.65;
            color: var(--text-dim);
        }

        .owner-note {
            margin: 1.25rem 0 0;
            max-width: 34rem;
            padding: .9rem 1.1rem;
            border-left: 3px solid var(--accent);
            border-radius: 0 .75rem .75rem 0;
            background: var(--surface-soft);
            color: var(--text);
            line-height: 1.6;
            white-space: pre-line;
        }

        /* ---------- The bake sheet (progress + checklist) ---------- */

        .bake-sheet {
            margin-top: 2rem;
            max-width: 34rem;
            padding: 1.25rem 1.25rem 1.1rem;
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            background: var(--surface);
            backdrop-filter: blur(6px);
        }

        .bake-sheet header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 1rem;
            font-size: .85rem;
            color: var(--text-dim);
        }

        .bake-sheet header strong {
            font-family: 'Fraunces', ui-serif, Georgia, serif;
            font-size: 1.05rem;
            color: var(--text);
        }

        .percent { font-variant-numeric: tabular-nums; color: var(--accent); font-weight: 600; }

        .bar {
            margin-top: .75rem;
            height: .6rem;
            border-radius: 999px;
            background: var(--track);
            overflow: hidden;
            cursor: help;
        }

        .bar span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--butter), var(--ember), var(--ember-deep));
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
            transition: width 1.2s ease;
        }

        .quip {
            min-height: 1.4em;
            margin: .6rem 0 0;
            font-size: .85rem;
            color: var(--text-dim);
            font-style: italic;
        }

        .steps { list-style: none; margin: 1rem 0 0; padding: 0; display: grid; gap: .55rem; }

        .steps li { display: flex; gap: .7rem; align-items: flex-start; font-size: .95rem; line-height: 1.4; }

        .steps .mark {
            flex: none;
            display: grid;
            place-items: center;
            width: 1.35rem;
            height: 1.35rem;
            margin-top: .05rem;
            border-radius: 50%;
            font-size: .75rem;
            font-weight: 700;
        }

        .steps .done .mark { background: var(--leaf); color: var(--crust-deep); }
        .steps .done span:last-child { color: var(--text-dim); }
        .steps .doing .mark { border: 2px solid var(--ember); border-top-color: transparent; animation: spin 1s linear infinite; }
        .steps .todo .mark { border: 2px dashed var(--line-strong); }
        .steps .todo span:last-child { color: var(--text-faint); }

        /* ---------- The oven ---------- */

        .oven-wrap { position: relative; display: grid; place-items: center; padding-top: 5rem; }

        .steam {
            position: absolute;
            top: 0;
            left: 50%;
            width: 16rem;
            height: 7rem;
            transform: translateX(-50%);
            pointer-events: none;
        }

        .steam path {
            fill: none;
            stroke: var(--steam);
            stroke-width: 5;
            stroke-linecap: round;
            opacity: 0;
            animation: steam 4.2s ease-in infinite;
        }

        .steam path:nth-child(2) { animation-delay: 1.4s; }
        .steam path:nth-child(3) { animation-delay: 2.8s; }

        .oven {
            position: relative;
            width: min(100%, 26rem);
            padding: 1rem 1rem 1.25rem;
            border-radius: 1.75rem 1.75rem 1.1rem 1.1rem;
            background: linear-gradient(180deg, var(--enamel), var(--enamel-shade));
            box-shadow:
                inset 0 -6px 0 rgba(0, 0, 0, .12),
                inset 0 3px 0 rgba(255, 255, 255, .6),
                0 40px 80px -20px rgba(0, 0, 0, .75),
                0 0 120px -10px rgba(255, 138, 61, .35);
            animation: rumble 5s ease-in-out infinite;
        }

        .panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .9rem;
            border-radius: .9rem;
            background: var(--steel);
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, .5);
        }

        .knob {
            position: relative;
            width: 2.3rem;
            height: 2.3rem;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #fff, #cdb99c 70%);
            box-shadow: 0 2px 0 rgba(0, 0, 0, .35);
            cursor: pointer;
            border: 0;
            padding: 0;
        }

        .knob::after {
            content: '';
            position: absolute;
            top: .25rem;
            left: 50%;
            width: 3px;
            height: .8rem;
            margin-left: -1.5px;
            border-radius: 2px;
            background: var(--ember-deep);
        }

        .knob.turning { animation: turn 9s ease-in-out infinite; }

        .display {
            flex: 1;
            min-width: 0;
            padding: .4rem .6rem;
            border-radius: .5rem;
            background: #120a05;
            color: var(--ember);
            font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-align: center;
            text-shadow: 0 0 8px rgba(255, 138, 61, .8);
            white-space: nowrap;
            overflow: hidden;
        }

        .door {
            position: relative;
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            background: linear-gradient(180deg, #efe0c8, #dcc6a6);
            box-shadow: inset 0 0 0 3px rgba(58, 42, 32, .15);
        }

        .handle {
            height: .7rem;
            margin: 0 12% .9rem;
            border-radius: 999px;
            background: linear-gradient(180deg, #8a7a6a, #4b3d31);
            box-shadow: 0 3px 0 rgba(0, 0, 0, .25);
        }

        .window {
            position: relative;
            aspect-ratio: 16 / 10;
            border-radius: .8rem;
            overflow: hidden;
            background: radial-gradient(ellipse at 50% 85%, #ffb45c 0%, #ff7a2f 35%, #8a2a0c 75%, #3a1206 100%);
            box-shadow: inset 0 0 0 6px #2a1a10, inset 0 0 40px rgba(0, 0, 0, .6);
            animation: flicker 2.6s ease-in-out infinite;
        }

        .window::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .22) 0 18%, transparent 18% 100%);
            pointer-events: none;
        }

        .element {
            position: absolute;
            left: 8%;
            right: 8%;
            height: 4px;
            border-radius: 2px;
            background: #ffdd8a;
            box-shadow: 0 0 12px 3px rgba(255, 170, 60, .9);
        }

        .element.top { top: 10%; }
        .element.bottom { bottom: 10%; }

        .rack {
            position: absolute;
            left: 6%;
            right: 6%;
            bottom: 22%;
            height: 3px;
            background: repeating-linear-gradient(90deg, #2a1a10 0 6px, transparent 6px 12px);
            opacity: .8;
        }

        /* The website itself, rising like a loaf and browning as it goes. */
        .loaf {
            position: absolute;
            left: 50%;
            bottom: calc(22% + 3px);
            width: 62%;
            height: 58%;
            transform-origin: 50% 100%;
            transform: translateX(-50%) scaleY(.55);
            border-radius: 1.2rem 1.2rem .35rem .35rem;
            background: linear-gradient(180deg, #f2cf8f, #d99a4e);
            box-shadow: inset 0 -8px 0 rgba(120, 60, 20, .25), 0 6px 14px rgba(0, 0, 0, .35);
            animation: rise 7s ease-in-out infinite, brown 7s ease-in-out infinite;
        }

        .loaf .chrome {
            display: flex;
            gap: 4px;
            padding: 8% 7% 0;
        }

        .loaf .chrome i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(90, 45, 15, .55);
        }

        .loaf .lines { padding: 8% 7% 0; display: grid; gap: 7%; }

        .loaf .lines b {
            display: block;
            height: 6px;
            border-radius: 3px;
            background: rgba(90, 45, 15, .35);
        }

        .loaf .lines b:nth-child(1) { width: 70%; }
        .loaf .lines b:nth-child(2) { width: 90%; }
        .loaf .lines b:nth-child(3) { width: 55%; }

        .loaf .score {
            position: absolute;
            top: -2px;
            left: 50%;
            width: 40%;
            height: 10px;
            transform: translateX(-50%);
            border-radius: 0 0 10px 10px;
            background: rgba(255, 240, 210, .55);
        }

        .no-peeking {
            position: absolute;
            right: -.6rem;
            top: 42%;
            transform: rotate(8deg);
            padding: .45rem .7rem;
            border-radius: .35rem;
            background: #fff8e8;
            color: var(--crust);
            font-family: 'Fraunces', ui-serif, Georgia, serif;
            font-weight: 700;
            font-size: .85rem;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .35);
        }

        .no-peeking::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 50%;
            width: 10px;
            height: 10px;
            margin-left: -5px;
            border-radius: 50%;
            background: var(--ember-deep);
        }

        .feet { display: flex; justify-content: space-between; padding: 0 12%; }
        .feet i { width: 2.2rem; height: .7rem; border-radius: 0 0 .5rem .5rem; background: var(--steel); }

        .oven-caption {
            margin-top: 1rem;
            font-size: .85rem;
            color: var(--text-dim);
            text-align: center;
        }

        .knob:focus-visible, .tray:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }

        footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .75rem 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--line);
            font-size: .85rem;
            color: var(--text-dim);
        }

        footer a { color: var(--accent); text-decoration: none; }
        footer a:hover { text-decoration: underline; }
        footer .credit { font-size: .75rem; opacity: .7; }

        /* ---------- Morning bake / night shift switch ----------
           A baking tray with one pastry on it: a round morning bun for the
           sun, sliding across to a croissant for the crescent moon. */

        .theme-switch {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-dim);
        }

        .tray {
            position: relative;
            flex: none;
            width: 4.6rem;
            height: 2.4rem;
            padding: 0;
            border: 0;
            border-radius: 999px;
            cursor: pointer;
            background: linear-gradient(180deg, #d9c2a0, #bfa27a);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, .25), inset 0 -2px 0 rgba(255, 255, 255, .35), 0 1px 0 rgba(255, 255, 255, .5);
            transition: background .5s ease;
        }

        /* The tray's rim. */
        .tray::before {
            content: '';
            position: absolute;
            inset: 4px;
            border-radius: inherit;
            border: 1px dashed rgba(90, 60, 30, .3);
        }

        :root[data-theme='dark'] .tray {
            background: linear-gradient(180deg, #3b2a4a, #221733);
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, .6), 0 0 0 1px rgba(251, 243, 230, .12);
        }

        :root[data-theme='dark'] .tray::before { border-color: rgba(251, 243, 230, .15); }

        /* Sprinkles, which are stars at night. */
        .tray .sprinkles {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity .5s ease;
        }

        .tray .sprinkles i {
            position: absolute;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #fff4c9;
            box-shadow: 0 0 4px #fff4c9;
            animation: twinkle 2.4s ease-in-out infinite;
        }

        .tray .sprinkles i:nth-child(1) { top: 28%; left: 18%; }
        .tray .sprinkles i:nth-child(2) { top: 62%; left: 30%; width: 3px; height: 3px; animation-delay: .8s; }
        .tray .sprinkles i:nth-child(3) { top: 36%; left: 42%; width: 2px; height: 2px; animation-delay: 1.6s; }

        :root[data-theme='dark'] .tray .sprinkles { opacity: 1; }

        .pastry {
            position: absolute;
            top: .2rem;
            left: .2rem;
            width: 2rem;
            height: 2rem;
            transition: transform .55s cubic-bezier(.5, 1.6, .5, 1);
        }

        :root[data-theme='dark'] .pastry { transform: translateX(2.2rem) rotate(-20deg); }

        .pastry svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            transition: opacity .35s ease, transform .55s ease;
        }

        .pastry .bun .rays { transform-origin: 16px 16px; animation: spin 18s linear infinite; }
        .pastry .croissant { opacity: 0; transform: scale(.6) rotate(40deg); }

        :root[data-theme='dark'] .pastry .bun { opacity: 0; transform: scale(.6) rotate(-40deg); }
        :root[data-theme='dark'] .pastry .croissant { opacity: 1; transform: none; }

        .tray:hover .pastry { filter: brightness(1.06); }
        .tray:active .pastry { scale: .92; }

        .theme-label .night { display: none; }
        :root[data-theme='dark'] .theme-label .day { display: none; }
        :root[data-theme='dark'] .theme-label .night { display: inline; }

        @media (max-width: 480px) {
            .theme-label { display: none; }
        }

        /* ---------- Motion ---------- */

        @keyframes twinkle {
            0%, 100% { opacity: .3; transform: scale(.7); }
            50% { opacity: 1; transform: scale(1.15); }
        }


        @keyframes rise {
            0% { transform: translateX(-50%) scaleY(.55) scaleX(.96); }
            55% { transform: translateX(-50%) scaleY(1.02) scaleX(1.02); }
            70% { transform: translateX(-50%) scaleY(.98) scaleX(1.01); }
            100% { transform: translateX(-50%) scaleY(.55) scaleX(.96); }
        }

        @keyframes brown {
            0%, 100% { filter: brightness(1.08) saturate(.85); }
            60% { filter: brightness(.9) saturate(1.2); }
        }

        @keyframes flicker {
            0%, 100% { filter: brightness(1); }
            40% { filter: brightness(1.12); }
            60% { filter: brightness(.94); }
        }

        @keyframes steam {
            0% { opacity: 0; transform: translateY(30px); }
            25% { opacity: .9; }
            100% { opacity: 0; transform: translateY(-40px); }
        }

        @keyframes rumble {
            0%, 92%, 100% { transform: translate(0, 0); }
            94% { transform: translate(-1px, 1px) rotate(-.3deg); }
            96% { transform: translate(1px, -1px) rotate(.3deg); }
            98% { transform: translate(-1px, 0); }
        }

        @keyframes turn {
            0%, 100% { transform: rotate(-40deg); }
            50% { transform: rotate(110deg); }
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @keyframes shimmer { to { background-position: -200% 0; } }

        @keyframes ping {
            70% { box-shadow: 0 0 0 .6rem rgba(255, 138, 61, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 138, 61, 0); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .loaf { transform: translateX(-50%) scaleY(.9); }
        }
    </style>
</head>
<body>
    <div class="backdrop" aria-hidden="true"></div>
    <div class="glow" aria-hidden="true"></div>

    <div class="page">
        <header class="topbar">
            <div class="brand">
                <x-app-logo-icon />
                <span>{{ $businessName }}</span>
            </div>

            <div class="theme-switch">
                <span class="theme-label" aria-hidden="true">
                    <span class="day">{{ __('Morning bake') }}</span>
                    <span class="night">{{ __('Night shift') }}</span>
                </span>

                <button
                    type="button"
                    class="tray"
                    data-theme-toggle
                    aria-pressed="false"
                    aria-label="{{ __('Night shift (dark mode)') }}"
                >
                    <span class="sprinkles" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="pastry" aria-hidden="true">
                        {{-- A round, smiling morning bun, sesame-topped, doing
                             its best impression of the sun. --}}
                        <svg class="bun" viewBox="0 0 32 32">
                            <defs>
                                <radialGradient id="bun-crust" cx="40%" cy="35%" r="70%">
                                    <stop offset="0" stop-color="#ffe0a3" />
                                    <stop offset=".55" stop-color="#eba853" />
                                    <stop offset="1" stop-color="#b8692a" />
                                </radialGradient>
                            </defs>
                            <g class="rays" stroke="#f5b537" stroke-width="2" stroke-linecap="round">
                                <path d="M16 1.5v3M16 27.5v3M1.5 16h3M27.5 16h3M5.7 5.7l2.1 2.1M24.2 24.2l2.1 2.1M5.7 26.3l2.1-2.1M24.2 7.8l2.1-2.1" />
                            </g>
                            <circle cx="16" cy="16" r="9.5" fill="url(#bun-crust)" />
                            <g fill="#fff8e6">
                                <ellipse cx="13.6" cy="10.6" rx=".9" ry=".5" transform="rotate(-20 13.6 10.6)" />
                                <ellipse cx="16.4" cy="9.6" rx=".9" ry=".5" />
                                <ellipse cx="19.2" cy="10.8" rx=".9" ry=".5" transform="rotate(25 19.2 10.8)" />
                            </g>
                            <circle cx="11.6" cy="18.2" r="1.5" fill="#ff8f6b" opacity=".55" />
                            <circle cx="20.4" cy="18.2" r="1.5" fill="#ff8f6b" opacity=".55" />
                            <ellipse cx="13.4" cy="15.4" rx="1" ry="1.25" fill="#5a2e0f" />
                            <ellipse cx="18.6" cy="15.4" rx="1" ry="1.25" fill="#5a2e0f" />
                            <path d="M13.8 18.4c1.2 1.3 3.2 1.3 4.4 0" stroke="#5a2e0f" stroke-width="1.2" stroke-linecap="round" fill="none" />
                        </svg>

                        {{-- A sleepy croissant, which is a crescent moon if
                             you squint. --}}
                        <svg class="croissant" viewBox="0 0 32 32">
                            <defs>
                                <linearGradient id="croissant-crust" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#ffd98c" />
                                    <stop offset="1" stop-color="#c7772f" />
                                </linearGradient>
                            </defs>
                            <path d="M25.5 20.5A11 11 0 1 1 13.2 5.2a8.6 8.6 0 0 0 12.3 15.3Z" fill="url(#croissant-crust)" stroke="#8f4e1b" stroke-width="1.1" stroke-linejoin="round" />
                            <path d="M9.6 9.4c1.2.9 1.9 2 2.1 3.4M13.4 24.8c.5-1.2 1.4-2.1 2.6-2.7" stroke="#8f4e1b" stroke-width="1" stroke-linecap="round" fill="none" opacity=".6" />
                            <path d="M7.4 16c.8.8 2 .8 2.8 0" stroke="#5a2e0f" stroke-width="1.1" stroke-linecap="round" fill="none" />
                            <path d="M8.2 20.2c.9.6 1.9.6 2.8 0" stroke="#5a2e0f" stroke-width="1" stroke-linecap="round" fill="none" />
                            <circle cx="6.6" cy="18.4" r="1.2" fill="#ff8f6b" opacity=".5" />
                            <text x="20.5" y="9" font-family="Georgia, serif" font-size="6" font-weight="700" fill="#fff4c9">
                                z
                            </text>
                            <text x="24.5" y="5.5" font-family="Georgia, serif" font-size="4.5" font-weight="700" fill="#fff4c9" opacity=".75">
                                z
                            </text>
                        </svg>
                    </span>
                </button>
            </div>
        </header>

        <main>
            <section class="intro">
                <span class="eyebrow"><span class="dot" aria-hidden="true"></span>{{ __('Now baking') }}</span>

                <h1>{!! __('Our website is <em>in the oven.</em>') !!}</h1>

                <p class="lede">
                    {{ __('We are baking it from scratch, same as everything else we make. Please do not open the door — it will sink. Fresh bakes, the full menu and online ordering are coming out soon.') }}
                </p>

                @if (filled($message))
                    <p class="owner-note">{{ $message }}</p>
                @endif
            </section>

            <section class="sheet">
                <div class="bake-sheet" style="margin-top: 0">
                    <header>
                        <strong>{{ __("Today's bake") }}</strong>
                        <span>{{ __('Progress') }} <span class="percent" data-percent>87%</span></span>
                    </header>

                    <div class="bar" data-bar title="{{ __('Patience. Good things take time to rise.') }}">
                        <span style="width: 87%"></span>
                    </div>

                    <p class="quip" data-quip aria-live="polite">{{ __('Preheating the internet to 350°F…') }}</p>

                    <ul class="steps">
                        <li class="done">
                            <span class="mark" aria-hidden="true">✓</span
                            ><span>{{ __('Menu kneaded, proofed and priced') }}</span>
                        </li>
                        <li class="done">
                            <span class="mark" aria-hidden="true">✓</span
                            ><span>{{ __('Photos dusted with icing sugar') }}</span>
                        </li>
                        <li class="doing">
                            <span class="mark" aria-hidden="true"></span
                            ><span>{{ __('Order form — rising nicely') }}</span>
                        </li>
                        <li class="todo">
                            <span class="mark" aria-hidden="true"></span
                            ><span>{{ __('Final glaze and a very serious taste test') }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="oven-wrap" aria-label="{{ __('An oven baking the new website') }}">
                <svg class="steam" viewBox="0 0 256 112" aria-hidden="true">
                    <path d="M88 110c-14-18 14-30 0-50s12-32 0-52" />
                    <path d="M128 110c-14-18 14-30 0-50s12-32 0-52" />
                    <path d="M168 110c-14-18 14-30 0-50s12-32 0-52" />
                </svg>

                <div class="oven">
                    <div class="panel">
                        <button
                            type="button"
                            class="knob turning"
                            data-knob
                            aria-label="{{ __('Turn up the heat') }}"
                        ></button>
                        <div class="display" data-display aria-live="off">350°F</div>
                        <button type="button" class="knob" data-knob aria-label="{{ __('Check the timer') }}"></button>
                    </div>

                    <div class="door">
                        <div class="handle" aria-hidden="true"></div>
                        <div class="window" aria-hidden="true">
                            <div class="element top"></div>
                            <div class="loaf">
                                <div class="score"></div>
                                <div class="chrome"><i></i><i></i><i></i></div>
                                <div class="lines"><b></b><b></b><b></b></div>
                            </div>
                            <div class="rack"></div>
                            <div class="element bottom"></div>
                        </div>
                        <div class="no-peeking" aria-hidden="true">{{ __('No peeking!') }}</div>
                    </div>

                    <div class="feet" aria-hidden="true"><i></i><i></i></div>
                </div>

                <p class="oven-caption" data-caption>{{ __('Baking at 350°F · Timer: “just a few more minutes”') }}</p>
            </section>
        </main>

        <footer>
            <div>
                © {{ now()->year }} {{ $businessName }}
                @if ($businessEmail !== '')
                    ·
                    <a href="mailto:{{ $businessEmail }}">{{ $businessEmail }}</a>
                @endif
                @if ($businessPhone !== '')
                    ·
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}">{{ $businessPhone }}</a>
                @endif
            </div>
            <div class="credit">{{ __('Background photo: Noport, CC BY-SA 4.0, via Wikimedia Commons') }}</div>
        </footer>
    </div>

    <script>
        (() => {
            const quips = @json($quips);
            const displays = @json($displays);
            const clicks = @json($clicks);

            const quip = document.querySelector('[data-quip]');
            const display = document.querySelector('[data-display]');
            const bar = document.querySelector('[data-bar] span');
            const percent = document.querySelector('[data-percent]');
            const caption = document.querySelector('[data-caption]');

            let progress = 87;
            let tick = 0;

            // Creeps towards 100 without ever getting there, like every oven timer.
            setInterval(() => {
                tick++;
                progress = Math.min(99.4, progress + (99.5 - progress) * 0.18);
                bar.style.width = progress.toFixed(1) + '%';
                percent.textContent = Math.floor(progress) + '%';
                quip.textContent = quips[tick % quips.length];
                display.textContent = displays[tick % displays.length];
            }, 3200);

            let clickCount = 0;
            const tease = () => {
                caption.textContent = clicks[clickCount++ % clicks.length];
                progress = Math.max(80, progress - 3);
            };

            document.querySelectorAll('[data-knob]').forEach((knob) => knob.addEventListener('click', tease));

            // Morning bake / night shift. Stored per visitor as a convenience;
            // a browser that refuses storage just starts in the light again.
            const toggle = document.querySelector('[data-theme-toggle]');
            const root = document.documentElement;
            const themeQuips = @json($themeQuips);

            const sync = () => {
                const dark = root.dataset.theme === 'dark';
                toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
            };

            toggle.addEventListener('click', () => {
                const dark = root.dataset.theme !== 'dark';
                root.dataset.theme = dark ? 'dark' : 'light';
                quip.textContent = dark ? themeQuips.night : themeQuips.day;

                try {
                    localStorage.setItem('coming-soon-theme', root.dataset.theme);
                } catch (e) {}

                sync();
            });

            sync();
            document.querySelector('[data-bar]').addEventListener('click', tease);
        })();
    </script>
</body>
</html>
