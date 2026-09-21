<x-layouts::app.sidebar :title="$title ?? null">
    {{-- role="main": <flux:main> renders a <div>, so without this the signed-in
         shell has no main landmark and assistive tech cannot jump to content. --}}
    <flux:main role="main"> {{ $slot }} </flux:main>
</x-layouts::app.sidebar>
