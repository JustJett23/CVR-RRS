@props(['room', 'floor'])

<button class="room-status-row" type="button" data-room-id="{{ $room['id'] }}">
    <span class="room-number">{{ $room['id'] }}</span>
    <span class="room-name-group"><strong>{{ $room['name'] }}</strong><small>{{ $floor }}{{ $floor === '1' ? 'st' : 'nd' }} floor · {{ $room['capacity'] }} seats</small></span>
    <x-status-badge :status="$room['status']" />
</button>
