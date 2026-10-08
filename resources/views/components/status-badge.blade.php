@props(['status'])

@php
    $labels = [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'reserved' => 'Reserved',
        'maintenance' => 'Maintenance',
        'unavailable' => 'Unavailable',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'status-badge '.$status]) }}>{{ $labels[$status] ?? 'Unknown status' }}</span>
