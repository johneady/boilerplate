@props([
    'embed',
    'poster' => null,
    'title' => '',
])

{{--
    The standard 16:9 video area. It shows a poster and a play button, and
    only loads the YouTube or Vimeo player when clicked: an embedded player
    costs about a megabyte of script on page load, which the brief's "fast
    loading" rules out for a video most visitors never play.
--}}
<div x-data="{ playing: false }" {{ $attributes->class('relative aspect-video overflow-hidden bg-neutral-900') }}>
    <template x-if="playing">
        <iframe
            src="{{ $embed }}"
            title="{{ $title }}"
            class="absolute inset-0 size-full"
            allow="autoplay; fullscreen; picture-in-picture"
            allowfullscreen
        ></iframe>
    </template>

    <button type="button" x-show="! playing" @click="playing = true" class="group absolute inset-0 size-full">
        @if (filled($poster))
            <img
                src="{{ $poster }}"
                alt=""
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover opacity-80"
            />
        @endif
        <span class="absolute inset-0 bg-neutral-950/30"></span>
        <span class="relative mx-auto flex size-20 items-center justify-center rounded-full bg-white text-neutral-950 transition group-hover:scale-105">
            <flux:icon.play variant="solid" class="size-8" />
        </span>
        <span class="sr-only">{{ __('Play video: :title', ['title' => $title]) }}</span>
    </button>
</div>
