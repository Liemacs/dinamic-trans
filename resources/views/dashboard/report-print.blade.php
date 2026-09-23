<x-layouts.print :title="'Darea de seamă · '.$report->from()->format('d.m.Y').' – '.$report->to()->format('d.m.Y')" :back="$back">
    <x-report.sheet :report="$report" :vehicle="$vehicle" />
</x-layouts.print>
