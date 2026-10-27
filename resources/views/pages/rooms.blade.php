@php($isAdmin = auth()->user()?->role === 'admin')
<x-layouts.app title="CVR" page-title="{{ $isAdmin ? 'Configure Rooms' : 'Room Directory' }}" current-page="rooms">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="{{ $isAdmin ? 'ROOM OPERATIONS' : 'WORKSPACE' }}" title="{{ $isAdmin ? 'Configure Rooms' : 'Room Directory' }}" description="{{ $isAdmin ? 'Add rooms and maintain the NPC Main Building inventory.' : 'Browse the campus room records by floor and current database status.' }}" action-label="Open 3D floor plan" :action-href="route('floor-plan')" />

        @if (session('status'))
            <div class="notice-panel reservation-feedback" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice-panel reservation-feedback" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($isAdmin)
            <section class="content-panel room-management-panel" aria-labelledby="room-management-title">
                <div class="content-panel-heading"><div><p class="eyebrow">{{ $building->name }}</p><h2 id="room-management-title">Add a room</h2></div><span class="live-indicator"><i></i> Database inventory</span></div>
                <form method="POST" action="{{ route('rooms.store') }}" class="room-config-form">
                    @csrf
                    <label class="filter-field"><span>Floor</span><select name="floor_id" required>@foreach ($floors as $floor)<option value="{{ $floor->id }}" @selected(old('floor_id') == $floor->id)>{{ $floor->name }}</option>@endforeach</select></label>
                    <label class="filter-field"><span>Room code</span><input name="code" maxlength="32" value="{{ old('code') }}" required></label>
                    <label class="filter-field"><span>Room name</span><input name="name" maxlength="255" value="{{ old('name') }}" required></label>
                    <label class="filter-field"><span>Type</span><input name="type" maxlength="48" value="{{ old('type') }}" placeholder="Classroom, lab, court…"></label>
                    <label class="filter-field"><span>Capacity</span><input type="number" name="capacity" min="0" max="100000" value="{{ old('capacity', 0) }}" required></label>
                    <label class="filter-field"><span>Reservation status</span><select name="status" required><option value="active">Available for requests</option><option value="unavailable">Unavailable</option><option value="maintenance">Maintenance</option></select></label>
                    <label class="filter-field room-config-description"><span>Description (optional)</span><input name="description" maxlength="1000" value="{{ old('description') }}"></label>
                    <button class="primary-button" type="submit"><x-icon name="plus" />Add room</button>
                </form>

                <div class="room-config-list-heading"><h3>Existing rooms</h3><span>{{ number_format($managedRooms->total()) }} records</span></div>
                <div class="room-config-list">
                    @foreach ($managedRooms as $room)
                        <details class="room-config-item">
                            <summary><span><strong>{{ $room->code }} · {{ $room->name }}</strong><small>{{ $room->floor->name }} · {{ ucfirst($room->status) }}</small></span><span class="room-config-edit">Edit</span></summary>
                            <form method="POST" action="{{ route('rooms.update', $room) }}" class="room-config-form room-config-edit-form">
                                @csrf
                                @method('PUT')
                                <label class="filter-field"><span>Floor</span><select name="floor_id" required>@foreach ($floors as $floor)<option value="{{ $floor->id }}" @selected((int) $room->floor_id === $floor->id)>{{ $floor->name }}</option>@endforeach</select></label>
                                <label class="filter-field"><span>Room code</span><input name="code" maxlength="32" value="{{ $room->code }}" required></label>
                                <label class="filter-field"><span>Room name</span><input name="name" maxlength="255" value="{{ $room->name }}" required></label>
                                <label class="filter-field"><span>Type</span><input name="type" maxlength="48" value="{{ $room->type }}"></label>
                                <label class="filter-field"><span>Capacity</span><input type="number" name="capacity" min="0" max="100000" value="{{ $room->capacity }}" required></label>
                                <label class="filter-field"><span>Reservation status</span><select name="status" required><option value="active" @selected($room->status === 'active')>Available for requests</option><option value="unavailable" @selected($room->status === 'unavailable')>Unavailable</option><option value="maintenance" @selected($room->status === 'maintenance')>Maintenance</option></select></label>
                                <label class="filter-field room-config-description"><span>Description (optional)</span><input name="description" maxlength="1000" value="{{ $room->description }}"></label>
                                <button class="secondary-button" type="submit">Save room</button>
                            </form>
                        </details>
                    @endforeach
                </div>
                <div class="activity-pagination">{{ $managedRooms->links() }}</div>
            </section>
        @endif

        <section class="content-panel" aria-labelledby="directory-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">SPACE INVENTORY</p><h2 id="directory-title">Rooms</h2></div>
                <span class="live-indicator"><i></i> Live database</span>
            </div>
            <div class="filter-row">
                <label class="filter-field"><span>Floor</span><select id="directory-floor" disabled><option value="">Loading floors…</option></select></label>
                <label class="filter-field"><span>Room type</span><select id="directory-type"><option value="all">All room types</option></select></label>
                <label class="filter-field"><span>Minimum capacity</span><input id="directory-capacity" type="number" min="1" placeholder="Any capacity"></label>
                <label class="filter-field"><span>Room status</span><select id="directory-status"><option value="all">All statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select></label>
                <span class="filter-note">Room records and availability are loaded from the database.</span>
            </div>
            <p class="directory-message" id="directory-message" role="status" aria-live="polite" data-endpoint="{{ route('dashboard.rooms') }}"><x-icon name="door-open" /><span>Loading room inventory…</span></p>
            <div class="room-directory-grid" id="room-directory-grid" aria-live="polite" data-floor-plan-url="{{ route('floor-plan') }}" @if (! in_array(auth()->user()?->role, ['student', 'admin'], true)) data-reservations-url="{{ route('reservations') }}" @endif data-schedule-url="{{ route('schedule') }}"></div>
            <p class="map-data-note">Room names, capacities, equipment, and statuses are read from the campus database.</p>
        </section>
    </main>
</x-layouts.app>
