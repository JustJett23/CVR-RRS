<x-layouts.app title="CVR" page-title="Room Directory" current-page="rooms">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="WORKSPACE" title="Room Directory" description="Browse the campus room records by floor and current database status." action-label="Open 3D floor plan" :action-href="route('floor-plan')" />

        <section class="content-panel" aria-labelledby="directory-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">SPACE INVENTORY</p><h2 id="directory-title">Rooms</h2></div>
                <span class="live-indicator"><i></i> Live database</span>
            </div>
            <div class="filter-row">
                <label class="filter-field"><span>Building</span><select id="directory-building" aria-label="Building"><option value="">All buildings</option>@foreach ($buildings as $building)<option value="{{ $building->id }}">{{ $building->name }}</option>@endforeach</select></label>
                <label class="filter-field"><span>Floor</span><select id="directory-floor"><option value="">All floors</option>@foreach ($floors as $floor)<option value="{{ $floor->id }}">{{ $floor->building->name }} · {{ $floor->name }}</option>@endforeach</select></label>
                <label class="filter-field"><span>Room type</span><select id="directory-type"><option value="">All room types</option>@foreach ($types as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></label>
                <label class="filter-field"><span>Minimum capacity</span><input id="directory-capacity" type="number" min="0" max="100000" placeholder="Any capacity"></label>
                <label class="filter-field"><span>Room status</span><select id="directory-status"><option value="all">All statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select></label>
                <span class="filter-note">Building, floor, type, capacity, and live status filters use database inventory.</span>
            </div>
            <p class="directory-message" id="directory-message" role="status" aria-live="polite" data-endpoint="{{ route('api.rooms.index') }}">Loading room inventory…</p>
            <div class="room-directory-grid" id="room-directory-grid" aria-live="polite" data-floor-plan-url="{{ route('floor-plan') }}" data-reservations-url="{{ route('reservations') }}" data-schedule-url="{{ route('schedule') }}"></div>
            <p class="map-data-note">Room names, capacities, equipment, and statuses are read from the campus database.</p>
        </section>
    </main>
</x-layouts.app>
