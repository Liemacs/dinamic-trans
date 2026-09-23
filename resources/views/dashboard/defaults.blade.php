@php
    use App\Support\DashboardNav;
@endphp

{{-- The heading and the line under it come from the nav map, so this page and
     the sidebar can never disagree on what the section is called. --}}
<x-layouts.dashboard
    :title="DashboardNav::heading('defaults')"
    :description="DashboardNav::description('defaults')"
>
    <livewire:dashboard.defaults />
</x-layouts.dashboard>
