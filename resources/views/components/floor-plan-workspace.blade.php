@props(['roomsByFloor', 'initialFloor' => null])

@php
    $initialRooms = $initialFloor === null
        ? collect($roomsByFloor)->flatten(1)->values()->all()
        : ($roomsByFloor[$initialFloor] ?? []);
@endphp

<section class="workspace-section" id="workspace-snapshot" aria-labelledby="workspace-title">
    <div class="section-heading">
        <div><p class="eyebrow">SPATIAL VIEW</p><h2 id="workspace-title">Workspace snapshot</h2></div>
        <span class="live-indicator"><i></i> Database availability</span>
    </div>

    <div class="workspace-grid">
        <article class="map-card">
            <div class="map-toolbar">
                <div class="map-title-group"><span class="map-title-mark" aria-hidden="true"><x-icon name="building" /></span><div><h3>Building overview</h3><p>NPC Main Building · live room data</p></div></div>
                <div class="map-filters">
                    <label class="select-wrap select-floor"><span class="sr-only">Floor</span><select id="floor-select" aria-label="Floor"><option value="all" @selected($initialFloor === null)>All mapped floors</option><option value="1" @selected($initialFloor === 1)>2F · Second floor</option><option value="2" @selected($initialFloor === 2)>3F · Third floor</option><option value="3" @selected($initialFloor === 3)>4F · Fourth floor</option><option value="4" @selected($initialFloor === 4)>Roof deck</option></select><span aria-hidden="true"><x-icon name="chevron-down" /></span></label>
                    <label class="select-wrap select-status"><span class="sr-only">Filter rooms by status</span><select id="status-select" aria-label="Filter rooms by status"><option value="all">All statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select><span aria-hidden="true">⌄</span></label>
                </div>
            </div>

            <div class="map-stage" id="map-stage">
                <div class="map-caption"><span class="map-caption-kicker">NPC · MAIN BUILDING · LIVE DATA</span><span class="map-caption-floor" id="map-floor-label">{{ [null => 'ALL MAPPED FLOORS', 1 => 'SECOND FLOOR · 2F', 2 => 'THIRD FLOOR · 3F', 3 => 'FOURTH FLOOR · 4F', 4 => 'ROOF DECK'][ $initialFloor ] ?? 'ALL MAPPED FLOORS' }}</span></div>
                <div id="floor-plan" data-campus-model="{{ asset('assets/npc_campus_room_stack.glb') }}" data-draco-url="{{ asset('assets/draco/') }}" data-floor-data-endpoint="{{ route('dashboard.floor-plan-data') }}" aria-label="Interactive 3D Navotas Polytechnic College room model. Click a floor to inspect it and drag to rotate." role="application" tabindex="0"></div>
                <button type="button" class="floor-back-button" id="floor-back" hidden>← Back to all floors</button>
                <div class="map-tooltip" id="map-tooltip" role="status" aria-live="polite" hidden></div>
                <div class="map-controls" aria-label="Floor plan view controls">
                    <x-map-control action="zoom-in" label="Zoom in" icon="plus" />
                    <x-map-control action="zoom-out" label="Zoom out" icon="minus" />
                    <span class="control-rule"></span>
                    <x-map-control action="center" label="Recenter floor plan" icon="center" />
                    <x-map-control action="rotate" label="Rotate floor plan" icon="rotate" />
                    <x-map-control action="viewpoint" label="Lock 2D plan view" icon="layers" />
                    <x-map-control action="fullscreen" label="View floor plan fullscreen" icon="maximize" />
                </div>
                <div class="map-help"><span class="drag-mark" aria-hidden="true">↔</span> Drag to rotate <span class="help-separator">·</span> Shift/Ctrl + drag to pan <span class="help-separator">·</span> Scroll to zoom</div>
            </div>

            <div class="map-legend" aria-label="Room status legend">
                <span class="legend-title">ROOM STATUS</span>
                <span class="legend-item"><i class="legend-dot available"></i>Available</span>
                <span class="legend-item"><i class="legend-dot occupied"></i>Occupied</span>
                <span class="legend-item"><i class="legend-dot reserved"></i>Reserved</span>
                <span class="legend-item"><i class="legend-dot maintenance"></i>Maintenance</span>
                <span class="legend-item"><i class="legend-dot unavailable"></i>Unavailable</span>
            </div>
            <p class="map-data-note">Room status and reservations are read from the CVR database.</p>
        </article>

        <aside class="snapshot-side" aria-label="Current room status and upcoming reservations">
            <section class="side-panel availability-panel" aria-labelledby="availability-title" aria-busy="false">
                <div class="panel-heading"><div class="panel-heading-title"><span class="panel-heading-mark" aria-hidden="true"><x-icon name="list-checks" /></span><span><p class="eyebrow">RIGHT NOW · DATABASE</p><h3 id="availability-title">Room availability</h3></span></div><span class="panel-count" id="room-count">{{ count($initialRooms) }} <small>rooms</small></span></div>
                <p class="room-load-message" id="room-load-message" role="status" hidden></p>
                <div class="room-status-list" id="room-status-list" data-endpoint="{{ route('dashboard.floor-plan-data') }}" aria-live="polite">
                    @foreach ($initialRooms as $room)
                        <x-room-row :room="$room" :floor="$room['floorName']" />
                    @endforeach
                    @if (count($initialRooms) === 0)
                        <p class="room-empty-state">No rooms are available right now.</p>
                    @endif
                </div>
                <div class="side-panel-foot"><span>Based on database status</span></div>
            </section>

            <section class="side-panel schedule-panel">
                <div class="panel-heading"><div class="panel-heading-title"><span class="panel-heading-mark upcoming-mark" aria-hidden="true"><x-icon name="calendar" /></span><span><p class="eyebrow">TODAY · DATABASE</p><h3>Upcoming</h3></span></div><span class="schedule-date" id="schedule-date">{{ now()->format('M j') }}</span></div>
                <ol class="upcoming-list" id="upcoming-list" data-endpoint="{{ route('dashboard.floor-plan-data') }}"><li class="upcoming-empty">No reservations scheduled for this floor today.</li></ol>
                <div class="side-panel-foot"><span>Approved and pending reservations</span></div>
            </section>

            <section class="selection-card" id="room-detail" aria-live="polite" hidden>
                <div class="selection-top"><span class="eyebrow">SELECTED SPACE</span><button type="button" class="selection-close" id="close-room-detail" aria-label="Close room details">×</button></div>
                <div class="selection-room-id" id="detail-room-id"></div>
                <h3 id="detail-room-name"></h3>
                <div class="detail-status" id="detail-status"></div>
                <dl class="room-facts"><div><dt>Capacity</dt><dd id="detail-capacity"></dd></div><div><dt>Equipment</dt><dd id="detail-equipment"></dd></div><div><dt>Next availability</dt><dd id="detail-next"></dd></div></dl>
                @unless (in_array(auth()->user()?->role, ['student', 'admin'], true))
                    <a class="secondary-button" href="{{ route('reservations') }}">Review reservation steps</a>
                @endunless
            </section>
        </aside>
    </div>

    {{ $slot }}
</section>
