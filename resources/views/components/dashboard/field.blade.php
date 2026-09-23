@props([
    // Livewire property, e.g. "distance_km".
    'model',
    'label' => null,
    'type' => 'text',   // 'text' | 'number' | 'textarea' | 'select'
    'help' => null,
    'live' => false,    // wire:model.live — for inputs a live result reads from
    'rows' => 3,
    'required' => false,
    // Unit shown inside the input's trailing edge: 'km', 'L/100km', '€'.
    'suffix' => null,
    'step' => null,
    'min' => null,
    'max' => null,
])

@php
    $directive = $live ? 'wire:model.live' : 'wire:model';

    // A number input's spinners are noise next to a unit suffix, and they invite
    // clicks that change a cost by 1 — typing is the only sensible input here.
    $base = implode(' ', [
        'w-full rounded-button border border-line bg-paper py-2 pl-3 text-sm text-copy transition-colors',
        'focus:border-forest focus:outline-none',
        '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
        $suffix ? 'pr-14' : 'pr-3',
    ]);

    $numeric = ['step' => $step, 'min' => $min, 'max' => $max];
    $numeric = array_filter($numeric, static fn ($value): bool => $value !== null);

    $flag = $required ? ['aria-required' => 'true'] : [];
@endphp

<div>
    @if ($label)
        <label for="field-{{ $model }}" class="block text-sm font-medium text-copy">
            {{ $label }}
            {{-- aria-hidden because aria-required on the input already carries the
                 meaning; without it a screen reader reads out "star". --}}
            @if ($required)
                <span class="text-red-700" aria-hidden="true">*</span>
            @endif
        </label>
    @endif
    @if ($help)
        <p class="mt-0.5 text-xs text-muted">{{ $help }}</p>
    @endif

    <div class="relative mt-1.5">
        @if ($type === 'textarea')
            <textarea id="field-{{ $model }}" {{ $directive }}="{{ $model }}" rows="{{ $rows }}" {{ $attributes->class($base)->merge($flag) }}></textarea>
        @elseif ($type === 'select')
            <select id="field-{{ $model }}" {{ $directive }}="{{ $model }}" {{ $attributes->class($base)->merge($flag) }}>
                {{ $slot }}
            </select>
        @else
            <input
                id="field-{{ $model }}"
                type="{{ $type }}"
                {{ $directive }}="{{ $model }}"
                {{ $attributes->class($base)->merge($numeric + $flag) }}
            >
        @endif

        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted">{{ $suffix }}</span>
        @endif
    </div>

    @error($model) <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
</div>
