@php
    use App\Support\DashboardNav;
@endphp

{{-- The heading and the line under it come from the nav map, so this page and
     the sidebar can never disagree on what the section is called. --}}
<x-layouts.dashboard
    :title="DashboardNav::heading('overview')"
    :description="DashboardNav::description('overview')"
>
    <livewire:dashboard.overview />
</x-layouts.dashboard>
