<x-filament-panels::page>
    {{-- The business overview widgets. Each checks the viewer may see it. --}}
    {{ $this->content }}

    {{-- The introduction to John Eady's work is for demo instances only: a
         client's live site must never carry it. --}}
    @unless (app()->environment('production'))
        <div
            x-data
            x-init="
                if (! window.localStorage.getItem('work-overview-introduced')) {
                    window.localStorage.setItem('work-overview-introduced', 'true');
                    setTimeout(() => $dispatch('open-modal', { id: 'work-overview' }), 10000);
                }
            "
        >
            {{-- Reopen strip: the introduction lives in a modal, so this stays on the page
         as the way back in. --}}
            <section class="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 rounded-2xl border border-blue-200 bg-blue-50/80 px-5 py-4 ring-1 ring-blue-600/10 dark:border-blue-900/60 dark:bg-blue-950/30 dark:ring-blue-400/10">
                <div class="flex min-w-0 items-center gap-4">
                    <img
                        src="{{ asset('images/dashboard/john-eady.jpeg') }}"
                        alt="John Eady"
                        width="44"
                        height="44"
                        class="size-11 shrink-0 rounded-xl object-cover ring-1 ring-blue-900/10 dark:ring-white/10"
                    />

                    <div class="min-w-0">
                        <h2 class="flex items-center gap-1.5 text-sm font-bold text-blue-900 dark:text-blue-100">
                            <x-filament::icon
                                icon="heroicon-s-information-circle"
                                class="size-4.5 shrink-0 text-blue-700 dark:text-blue-400"
                            />
                            {{ __('The figures above are demo data') }}
                        </h2>
                        <p class="mt-0.5 text-xs font-semibold text-blue-800/80 dark:text-blue-200/80">
                            {{ __('In the finished product they come from your real sales, customers and messages.') }}
                            <span class="font-normal text-blue-700 dark:text-blue-300/80">The panel itself is a live work sample by John Eady. See the story behind both.</span>
                        </p>
                    </div>
                </div>

                <x-filament::button
                    icon="heroicon-m-sparkles"
                    size="sm"
                    x-on:click="$dispatch('open-modal', { id: 'work-overview' })"
                >
                    Show introduction
                </x-filament::button>
            </section>

            {{-- Introduction modal, carried over from the one on johneady.duckdns.org. --}}
            <x-filament::modal id="work-overview" width="4xl" sticky-footer>
                {{-- Hero, mirroring the introduction modal's header. --}}
                <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-300 via-blue-200 to-indigo-300 px-5 py-6 sm:px-6 dark:from-blue-900/70 dark:via-slate-900/80 dark:to-slate-900">
                    <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true">
                        <svg class="h-full w-full" viewBox="0 0 800 200" preserveAspectRatio="xMidYMid slice" fill="none">
                            <g stroke="#2563eb" stroke-opacity="0.25">
                                <circle cx="700" cy="40" r="140" />
                                <circle cx="700" cy="40" r="90" />
                                <path d="M40 180 C 260 120, 480 130, 690 55" stroke-width="1.5" stroke-dasharray="5 7" />
                            </g>
                        </svg>
                    </div>

                    <div class="relative flex flex-wrap items-start gap-5">
                        <img
                            src="{{ asset('images/dashboard/john-eady.jpeg') }}"
                            alt="John Eady"
                            width="64"
                            height="64"
                            class="size-16 shrink-0 rounded-2xl object-cover shadow-lg ring-1 ring-slate-900/10 dark:ring-white/20"
                        />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold tracking-widest text-emerald-700 uppercase ring-1 ring-emerald-600/30 ring-inset dark:bg-emerald-400/15 dark:text-emerald-300 dark:ring-emerald-400/30">
                                    <span class="size-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                    Available for work
                                </p>

                                <a
                                    href="https://www.upwork.com/freelancers/~017251040a29ffc859"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-semibold tracking-widest text-slate-700 uppercase ring-1 ring-slate-900/10 transition ring-inset hover:bg-white dark:bg-white/10 dark:text-slate-200 dark:ring-white/20 dark:hover:bg-white/15"
                                >
                                    <x-filament::icon
                                        icon="heroicon-s-check-badge"
                                        class="size-3.5 text-blue-600 dark:text-sky-300"
                                    />
                                    Hire me on Upwork
                                </a>
                            </div>

                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                                John Eady
                            </h3>
                            <p class="mt-1 text-base font-medium text-blue-700 dark:text-sky-300">
                                I build your website, get it online, and keep it running.
                            </p>
                            <p class="mt-1.5 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Laravel &middot; Livewire &middot; Filament &middot; PHP
                            </p>
                        </div>
                    </div>
                </section>

                <p class="mt-4 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                    A lot of developers hand over the code and leave the rest to you. I stay with it until the thing is
                    genuinely working at an address your customers can visit: the site itself, the data behind it, the
                    admin screens your team runs it from, plus the domain, the security certificate and the server. One
                    person to ask, and nothing dropped between the code being finished and the site being up.
                </p>

                {{-- Work sample --}}
                <section class="mt-4 rounded-xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-900/50 dark:bg-blue-950/30">
                    <h4 class="flex items-center gap-2 text-base font-bold text-blue-900 dark:text-blue-100">
                        <x-filament::icon icon="heroicon-s-eye" class="size-5 shrink-0" />
                        You are looking at the work sample
                    </h4>
                    <p class="mt-2 text-sm leading-relaxed text-blue-900/80 dark:text-blue-200/80">
                        Not a screenshot or a case study. This panel, the secure sign-in behind it and the site it
                        belongs to are all mine, so have a proper look around. Behind the scenes there is passkey
                        sign-in (a fingerprint or face instead of a password), an extra verification step like your bank
                        asks for, an admin area for managing users, and settings your team can change themselves without
                        calling a developer. Built, hosted and kept running by me.
                    </p>
                </section>

                {{-- How I work --}}
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['heroicon-s-chat-bubble-left-right', 'Quick, honest answers', 'I answer messages quickly during my working hours, in plain English rather than jargon. Ask me where something stands at any point and you will get a straight answer.'],
                        ['heroicon-s-currency-dollar', 'Agreed price up front', 'We settle the scope and the number for each stage before I start it. If you want to change something along the way, I will tell you what it costs first, so the invoice never surprises you.'],
                        ['heroicon-s-rocket-launch', 'Online, not just built', 'Working on my laptop does not count. It is finished when it is running on your domain, backed up and monitored, and you have clicked through it yourself and are happy.'],
                        ['heroicon-s-key', 'Everything is yours', 'The code, the repository, the domain and the server logins are in your name from day one. Hand the project to someone else whenever you like and nothing about it will be awkward.'],
                    ] as [$icon, $heading, $body])
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <div class="flex items-center gap-2">
                                <x-filament::icon
                                    :icon="$icon"
                                    class="size-4 shrink-0 text-blue-600 dark:text-blue-400"
                                />
                                <h5 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h5>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- What I build --}}
                <section class="mt-5">
                    <h4 class="flex items-center gap-2 text-sm font-semibold tracking-widest text-blue-700 uppercase dark:text-blue-400">
                        <x-filament::icon icon="heroicon-s-cpu-chip" class="size-4" />
                        What I can build for you
                    </h4>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['heroicon-s-code-bracket', 'The site or app itself', ['Built around how your business actually works', 'Admin panels your team can use', 'Connected to the software you already use', 'Automatic jobs, like reports that send themselves']],
                            ['heroicon-s-circle-stack', 'Your data', ['Designing how it is stored', 'Moving data off an old system', 'Reports and spreadsheet exports', 'Keeping it quick as it grows']],
                            ['heroicon-s-shield-check', 'Doing it properly', ['Automatic checks that catch problems before your customers do', 'Works on phones and desktops', 'Secure sign-in, including passkeys', 'Code the next developer can follow']],
                        ] as [$icon, $heading, $items])
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <div class="flex items-center gap-2">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-blue-800 text-white">
                                        <x-filament::icon :icon="$icon" class="size-4" />
                                    </span>
                                    <h5 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h5>
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
                <section class="mt-5">
                    <h4 class="flex flex-wrap items-center gap-2 text-sm font-semibold tracking-widest text-blue-700 uppercase dark:text-blue-400">
                        <x-filament::icon icon="heroicon-s-server-stack" class="size-4" />
                        Getting it online and keeping it there
                        <span class="rounded-full border border-blue-600/30 px-2 py-0.5 text-[0.625rem] font-medium tracking-normal text-blue-700 normal-case dark:border-blue-400/30 dark:text-blue-400">
                            All optional
                        </span>
                    </h4>

                    <p class="mt-2 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                        Pick and choose whichever of these you want. Take them all, take none, or bring your own hosting
                        and I will work with what you already have.
                    </p>

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
                                <h5 class="mt-3 text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h5>
                                <p class="mt-1.5 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                                    {{ $body }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Footer call to action, mirroring the modal's footer. --}}
                <x-slot name="footer">
                    <div class="flex w-full flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Want something like this for your business?
                        </p>

                        <div class="flex items-center gap-2">
                            <x-filament::button
                                color="gray"
                                x-on:click="$dispatch('close-modal', { id: 'work-overview' })"
                            >
                                Close
                            </x-filament::button>

                            <x-filament::button
                                tag="a"
                                href="https://www.upwork.com/freelancers/~017251040a29ffc859"
                                target="_blank"
                                rel="noopener noreferrer"
                                icon="heroicon-m-arrow-top-right-on-square"
                                icon-position="after"
                            >
                                Message me on Upwork
                            </x-filament::button>
                        </div>
                    </div>
                </x-slot>
            </x-filament::modal>
        </div>
    @endunless
</x-filament-panels::page>
