<x-filament-panels::page>
    {{-- Hero, carried over from the introduction modal's header on johneady.duckdns.org. --}}
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 px-6 py-7 shadow-lg sm:px-8">
        <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true">
            <svg class="h-full w-full" viewBox="0 0 800 200" preserveAspectRatio="xMidYMid slice" fill="none">
                <g stroke="#38bdf8" stroke-opacity="0.35">
                    <circle cx="700" cy="40" r="140" />
                    <circle cx="700" cy="40" r="90" />
                    <path d="M40 180 C 260 120, 480 130, 690 55" stroke-width="1.5" stroke-dasharray="5 7" />
                </g>
            </svg>
        </div>

        <div class="relative flex flex-wrap items-start gap-5">
            <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-black text-white shadow-lg ring-1 ring-white/20">
                JE
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-2.5 py-1 text-[11px] font-semibold tracking-widest text-emerald-300 uppercase ring-1 ring-emerald-400/30 ring-inset">
                        <span class="size-1.5 rounded-full bg-emerald-400"></span>
                        Available for work
                    </p>

                    <a
                        href="https://www.upwork.com/freelancers/~017251040a29ffc859"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold tracking-widest text-slate-200 uppercase ring-1 ring-white/20 transition ring-inset hover:bg-white/15 hover:text-white"
                    >
                        <x-filament::icon icon="heroicon-s-check-badge" class="size-3.5 text-sky-300" />
                        Hire me on Upwork
                    </a>
                </div>

                <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">John Eady</h2>
                <p class="mt-1 text-base font-medium text-sky-300">
                    I build web apps and get them online, from the code to the domain to the server.
                </p>
                <p class="mt-1.5 text-xs font-medium tracking-wider text-slate-400 uppercase">
                    Laravel &middot; Livewire &middot; Filament &middot; PHP
                </p>
            </div>
        </div>
    </section>

    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
        A lot of developers hand over a repository and leave the rest to you. I stay on it until the thing is genuinely
        working at an address your customers can visit: the app itself, the database behind it, the admin screens your
        team runs it from, plus the domain, the SSL and the server. One person to ask, and nothing dropped between the
        code being finished and the site being up.
    </p>

    {{-- Work sample --}}
    <section class="rounded-xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-900/50 dark:bg-blue-950/30">
        <h3 class="flex items-center gap-2 text-base font-bold text-blue-900 dark:text-blue-100">
            <x-filament::icon icon="heroicon-s-eye" class="size-5 shrink-0" />
            You are looking at the work sample
        </h3>
        <p class="mt-2 text-sm leading-relaxed text-blue-900/80 dark:text-blue-200/80">
            Not a screenshot or a case study. This panel, the authentication behind it and the site it belongs to are
            all mine, so have a proper look around. Behind the scenes there is passkey sign-in, two-factor
            authentication, an admin panel for managing users, and settings your team can change without a deploy.
            Built, hosted and kept running by me.
        </p>
    </section>

    {{-- How I work --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['heroicon-s-chat-bubble-left-right', 'Responsive', 'I answer messages quickly during my working hours, in plain English rather than jargon. Ask me where something stands at any point and you will get a straight answer.'],
            ['heroicon-s-currency-dollar', 'Agreed price up front', 'We settle the scope and the number for each stage before I start it. If you want to change something along the way, I will tell you what it costs first, so the invoice never surprises you.'],
            ['heroicon-s-rocket-launch', 'Online, not just built', 'Working on my laptop does not count. It is finished when it is running on your domain, backed up and monitored, and you have clicked through it yourself and are happy.'],
            ['heroicon-s-key', 'Everything is yours', 'The code, the repository, the domain and the server logins are in your name from day one. Hand the project to someone else whenever you like and nothing about it will be awkward.'],
        ] as [$icon, $heading, $body])
            <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="flex items-center gap-2">
                    <x-filament::icon :icon="$icon" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                    <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h4>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
            </div>
        @endforeach
    </div>

    {{-- What I build --}}
    <section>
        <h3 class="flex items-center gap-2 text-sm font-semibold tracking-widest text-blue-700 uppercase dark:text-blue-400">
            <x-filament::icon icon="heroicon-s-cpu-chip" class="size-4" />
            What I can build for you
        </h3>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['heroicon-s-code-bracket', 'The application', ['Laravel and PHP', 'Admin panels your team can use', 'Connecting other services and APIs', 'Jobs that run on a schedule']],
                ['heroicon-s-circle-stack', 'Your data', ['Designing how it is stored', 'Moving data off an old system', 'Reports and spreadsheet exports', 'Keeping it quick as it grows']],
                ['heroicon-s-shield-check', 'Doing it properly', ['Tests that catch breakages', 'Works on phones and desktops', 'Secure sign-in, including passkeys', 'Code the next developer can follow']],
            ] as [$icon, $heading, $items])
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-blue-800 text-white">
                            <x-filament::icon :icon="$icon" class="size-4" />
                        </span>
                        <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h4>
                    </div>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-1.5 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                                <x-filament::icon
                                    icon="heroicon-o-check"
                                    class="mt-px size-3.5 shrink-0 text-blue-600 dark:text-blue-400"
                                />
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Hosting --}}
    <section>
        <h3 class="flex items-center gap-2 text-sm font-semibold tracking-widest text-blue-700 uppercase dark:text-blue-400">
            <x-filament::icon icon="heroicon-s-server-stack" class="size-4" />
            Getting it online and keeping it there
        </h3>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['heroicon-s-globe-alt', 'Your domain', 'I register the name or move an existing one across, point it at the site, and set up the padlock so browsers trust it. Email sending is configured properly too, so your mail lands in inboxes instead of spam.'],
                ['heroicon-s-server', 'Somewhere to run it', 'I set up the server, install everything it needs and get the site onto it. Updates go out without taking the site down, so your customers never hit a maintenance page while I am working.'],
                ['heroicon-s-lock-closed', 'Keeping it safe', 'Backups run automatically to somewhere separate from the server. I get alerted if the site goes down or throws errors, the server is locked down sensibly, and there is always a way back if a change goes wrong.'],
            ] as [$icon, $heading, $body])
                <div class="rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-blue-600/30 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-blue-800 text-white">
                        <x-filament::icon :icon="$icon" class="size-5" />
                    </span>
                    <h4 class="mt-3 text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h4>
                    <p class="mt-1.5 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Footer call to action, mirroring the modal's footer. --}}
    <footer class="flex flex-wrap items-center justify-between gap-4 rounded-xl border-t border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/80">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Want something like this built and running on your own domain?
        </p>

        <x-filament::button
            tag="a"
            href="https://www.upwork.com/freelancers/~017251040a29ffc859"
            target="_blank"
            rel="noopener noreferrer"
            icon="heroicon-m-arrow-top-right-on-square"
            icon-position="after"
        >
            View Upwork profile
        </x-filament::button>
    </footer>
</x-filament-panels::page>
