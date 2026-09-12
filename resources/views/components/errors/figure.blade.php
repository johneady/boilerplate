@props(['status'])

{{--
    The whimsical figure for each error page.

    Inline SVG rather than an image file, for the same reason the rest of these
    pages avoid the database: a 500 page must not depend on the storage disk or
    a build artefact resolving. Inline also means the art inherits currentColor
    and the theme palette instead of shipping a second set of colours.

    Each figure is drawn on a 200x160 canvas, decorative, and hidden from
    assistive technology -- the heading and message already say everything the
    picture does. Motion is used sparingly and every animation is wrapped in a
    prefers-reduced-motion guard.
--}}
<div {{ $attributes->class('relative flex') }} aria-hidden="true">
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .err-float {
                animation: err-float 4s ease-in-out infinite;
            }
            .err-swing {
                animation: err-swing 3.5s ease-in-out infinite;
                transform-origin: 100px 34px;
            }
            .err-tick {
                animation: err-tick 6s linear infinite;
                transform-origin: 100px 78px;
            }
            .err-pulse {
                animation: err-pulse 2.6s ease-in-out infinite;
            }
            .err-spin {
                animation: err-spin 9s linear infinite;
                transform-origin: 100px 82px;
            }
            .err-drift {
                animation: err-drift 5s ease-in-out infinite;
            }
        }

        @keyframes err-float {
            0%,
            100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-6px);
            }
        }
        @keyframes err-swing {
            0%,
            100% {
                transform: rotate(-6deg);
            }
            50% {
                transform: rotate(6deg);
            }
        }
        @keyframes err-tick {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes err-pulse {
            0%,
            100% {
                opacity: 0.35;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.06);
            }
        }
        @keyframes err-spin {
            to {
                transform: rotate(-360deg);
            }
        }
        @keyframes err-drift {
            0%,
            100% {
                transform: translateX(0);
            }
            50% {
                transform: translateX(5px);
            }
        }
    </style>

    {{--
        viewBox cropped to the drawing rather than the full 200x160 canvas:
        the figures sit in the middle of it, so an untrimmed box pads the page
        with empty space on both sides and pushes the heading down.

        Height and crop are a pair. The box is 144 units wide and the art is
        drawn at ~36px of detail (eyes, mouths), so h-36 is the point where
        those read at a glance without the figure competing with the heading:
        at h-28 the 500's face was illegible, at h-40 the whole block crowded
        the fold on a laptop.
    --}}
    <svg viewBox="28 24 144 120" fill="none" class="h-36 w-auto">
        <defs>
            <linearGradient id="err-grad-{{ $status }}" x1="0" y1="0" x2="200" y2="160" gradientUnits="userSpaceOnUse">
                @switch ($status)
                    @case (403)
                        <stop stop-color="#fbbf24"
                        /><stop offset="1" stop-color="#ea580c" />
                        @break
                    @case (404)
                        <stop stop-color="#38bdf8"
                        /><stop offset="1" stop-color="#6366f1" />
                        @break
                    @case (419)
                        <stop stop-color="#a78bfa"
                        /><stop offset="1" stop-color="#d946ef" />
                        @break
                    @case (429)
                        <stop stop-color="#fb7185"
                        /><stop offset="1" stop-color="#dc2626" />
                        @break
                    @case (500)
                        <stop stop-color="#fb7185"
                        /><stop offset="1" stop-color="#b91c1c" />
                        @break
                    @default
                        <stop stop-color="#2dd4bf"
                        /><stop offset="1" stop-color="#059669" />
                @endswitch
            </linearGradient>
        </defs>

        {{--
            A soft blob so the art sits on something rather than floating.
            Its height is per-figure: the drawings do not all reach the same
            baseline (the 500's cable and the 429's palm stop well short of
            the 404 pin's tip), and one shared y left a visible gap under the
            shorter ones.
        --}}
        @php
            $groundY = match ((int) $status) {
                404, 503 => 126,
                419 => 128,
                403 => 130,
                429 => 118,
                default => 110,
            };
        @endphp

        <ellipse cx="100" cy="{{ $groundY }}" rx="48" ry="7" fill="url(#err-grad-{{ $status }})" opacity=".18" />

        @switch ($status)
            @case (403)
                {{-- A padlock with a friendly face, shackle swinging "no". --}}
                <g class="err-float">
                    <path
                        class="err-swing"
                        d="M78 62V48a22 22 0 0 1 44 0v14"
                        stroke="url(#err-grad-403)"
                        stroke-width="9"
                        stroke-linecap="round"
                    />
                    <rect x="60" y="62" width="80" height="62" rx="14" fill="url(#err-grad-403)" />
                    <circle cx="86" cy="88" r="5" fill="#fff" />
                    <circle cx="114" cy="88" r="5" fill="#fff" />
                    {{-- A flat, unimpressed mouth. --}}
                    <path d="M86 106h28" stroke="#fff" stroke-width="5" stroke-linecap="round" />
                </g>
                @break
            @case (404)
                {{-- A map pin that has lost the map, peering through a glass. --}}
                <g class="err-float">
                    <path
                        d="M100 36c-17 0-30 13-30 29 0 22 30 51 30 51s30-29 30-51c0-16-13-29-30-29Z"
                        fill="url(#err-grad-404)"
                    />
                    <circle cx="100" cy="64" r="12" fill="#fff" />
                    <circle cx="96" cy="62" r="2.5" fill="#0f172a" />
                    <circle cx="105" cy="62" r="2.5" fill="#0f172a" />
                </g>
                {{-- Dashed trail wandering off, because the page went somewhere. --}}
                <path
                    class="err-drift"
                    d="M38 128c18-10 34 6 52-2s26-16 44-10"
                    stroke="url(#err-grad-404)"
                    stroke-width="4"
                    stroke-linecap="round"
                    stroke-dasharray="2 10"
                    opacity=".65"
                />
                @break
            @case (419)
                {{-- An hourglass-faced clock, hands spinning past the session. --}}
                <g class="err-float">
                    <circle cx="100" cy="78" r="44" fill="url(#err-grad-419)" />
                    <circle cx="100" cy="78" r="34" fill="#fff" fill-opacity=".92" />

                    {{--
                        The hand sweeps BEHIND the face and is kept short, so
                        it reads as a clock rather than cutting between the
                        eyes -- at r=20 from the centre it landed on the brow
                        and turned the whole figure into a frown.
                    --}}
                    <g class="err-tick">
                        <path d="M100 78V64" stroke="#a855f7" stroke-width="4" stroke-linecap="round" opacity=".55" />
                    </g>

                    <circle cx="88" cy="72" r="3.5" fill="#0f172a" />
                    <circle cx="112" cy="72" r="3.5" fill="#0f172a" />

                    {{-- A wry, slightly lopsided "ah well" mouth, curving UP. --}}
                    <path
                        d="M89 91c5 6 17 6 22-1"
                        stroke="#0f172a"
                        stroke-width="4"
                        stroke-linecap="round"
                        fill="none"
                    />
                </g>
                @break
            @case (429)
                {{-- A stop-sign palm, with speed lines: slow down. --}}
                <g class="err-float">
                    <path d="M100 26 130 40v34c0 24-13 41-30 48-17-7-30-24-30-48V40Z" fill="url(#err-grad-429)" />
                    <path
                        d="M88 74v-16a5 5 0 0 1 10 0v12m0-4a5 5 0 0 1 10 0v6m0-4a5 5 0 0 1 9 0v18c0 10-7 17-17 17s-17-7-17-17v-6"
                        stroke="#fff"
                        stroke-width="4.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        fill="none"
                    />
                </g>
                <g class="err-pulse" stroke="url(#err-grad-429)" stroke-width="4" stroke-linecap="round">
                    <path d="M40 62h16M34 80h22M40 98h16" />
                </g>
                @break
            @case (500)
                {{-- An unplugged cable: the machine has come apart, politely. --}}
                <g class="err-float">
                    <rect x="32" y="66" width="52" height="30" rx="10" fill="url(#err-grad-500)" />
                    <path d="M84 74h10M84 88h10" stroke="url(#err-grad-500)" stroke-width="7" stroke-linecap="round" />
                    <rect x="116" y="66" width="52" height="30" rx="10" fill="url(#err-grad-500)" />
                    <path
                        d="M106 74h10M106 88h10"
                        stroke="url(#err-grad-500)"
                        stroke-width="7"
                        stroke-linecap="round"
                    />
                    {{-- The gap between the two halves, with a couple of sparks. --}}
                    <g class="err-pulse" fill="#facc15">
                        <circle cx="100" cy="60" r="3.5" />
                        <circle cx="94" cy="102" r="2.5" />
                        <circle cx="108" cy="98" r="2" />
                    </g>
                    <circle cx="52" cy="81" r="3.5" fill="#fff" />
                    <circle cx="66" cy="81" r="3.5" fill="#fff" />
                    <path
                        d="M50 92a10 10 0 0 1 18 0"
                        stroke="#fff"
                        stroke-width="4"
                        stroke-linecap="round"
                        fill="none"
                    />
                </g>
                @break
            @default
                {{-- 503: a cog being tightened. Back shortly. --}}
                <g class="err-float">
                    <g class="err-spin">
                        <path
                            d="M100 44a38 38 0 0 1 12 2l4-12 14 6-4 12a38 38 0 0 1 9 9l12-4 6 14-12 4a38 38 0 0 1 0 12l12 4-6 14-12-4a38 38 0 0 1-9 9l4 12-14 6-4-12a38 38 0 0 1-12 0l-4 12-14-6 4-12a38 38 0 0 1-9-9l-12 4-6-14 12-4a38 38 0 0 1 0-12l-12-4 6-14 12 4a38 38 0 0 1 9-9l-4-12 14-6 4 12a38 38 0 0 1 12-2Z"
                            fill="url(#err-grad-503)"
                            opacity=".28"
                        />
                    </g>
                    <circle cx="100" cy="82" r="30" fill="url(#err-grad-503)" />
                    <circle cx="90" cy="76" r="4" fill="#fff" />
                    <circle cx="110" cy="76" r="4" fill="#fff" />
                    {{-- A confident little smile: this one is temporary. --}}
                    <path
                        d="M88 92a14 14 0 0 0 24 0"
                        stroke="#fff"
                        stroke-width="4.5"
                        stroke-linecap="round"
                        fill="none"
                    />
                </g>
        @endswitch
    </svg>
</div>
