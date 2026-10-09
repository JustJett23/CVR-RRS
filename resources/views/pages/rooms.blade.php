<x-layouts.app title="CVR" page-title="Room Directory" current-page="rooms">
    <main class="dashboard page-content">
        <x-page-title eyebrow="WORKSPACE" title="Room Directory" description="Browse the illustrative campus room list by floor and current sample status." action-label="Open 3D floor plan" :action-href="route('floor-plan')" />

        <section class="content-panel" aria-labelledby="directory-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">SPACE INVENTORY</p><h2 id="directory-title">Rooms</h2></div>
                <span class="sample-tag">Illustrative data</span>
            </div>
            <div class="filter-row">
                <label class="filter-field"><span>Building</span><select disabled aria-disabled="true" title="Building inventory has not been verified"><option>Main Building · sample</option></select></label>
                <label class="filter-field"><span>Floor</span><select id="directory-floor"><option value="1">1st Floor</option><option value="2">2nd Floor</option></select></label>
                <label class="filter-field"><span>Room type</span><select disabled aria-disabled="true" title="Room type metadata is not connected"><option>Types not verified</option></select></label>
                <label class="filter-field"><span>Minimum capacity</span><input type="number" min="1" placeholder="Not connected" disabled aria-disabled="true" title="Capacity filter needs verified inventory"></label>
                <label class="filter-field"><span>Room status</span><select id="directory-status"><option value="all">All statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select></label>
                <span class="filter-note">Floor and status filters use sample data. Building, type, and capacity need verified inventory.</span>
            </div>
            <p class="directory-message" id="directory-message" role="status" aria-live="polite" data-endpoint="{{ route('dashboard.rooms') }}">Loading illustrative room inventory…</p>
            <div class="room-directory-grid" id="room-directory-grid" aria-live="polite" data-floor-plan-url="{{ route('floor-plan') }}" data-reservations-url="{{ route('reservations') }}" data-schedule-url="{{ route('schedule') }}"></div>
            <p class="map-data-note">Room names, capacities, equipment, and statuses shown here are sample content. Confirm against the official campus inventory before publishing.</p>
        </section>
    </main>
</x-layouts.app>
