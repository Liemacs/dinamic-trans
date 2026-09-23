@php
    use App\Support\DashboardNav;
@endphp

<x-layouts.dashboard
    :title="DashboardNav::heading('report')"
    :description="DashboardNav::description('report')"
>
    <livewire:dashboard.report />
</x-layouts.dashboard>
