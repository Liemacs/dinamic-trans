{{-- The calculator opened over a row that already exists.

     Not a section of its own: it is reached from a row in Rute salvate, so the
     heading names the run rather than the screen, and the way back sits in the
     topbar where the section pages keep their actions. --}}
<x-layouts.dashboard title="Modifică ruta" :description="$route->label()">
    <x-slot:actions>
        <a
            href="{{ route('dashboard.routes') }}"
            class="inline-flex items-center gap-1.5 rounded-button border border-line px-3.5 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm"
        >Înapoi la rute</a>
    </x-slot:actions>

    <livewire:dashboard.calculator :route-id="$route->id" />
</x-layouts.dashboard>
