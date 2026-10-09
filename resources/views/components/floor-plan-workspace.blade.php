@props(['roomsByFloor', 'initialFloor' => 1])

<section class="workspace-section" id="workspace-snapshot" aria-labelledby="workspace-title">
    <div class="section-heading">
        <div><p class="eyebrow">SPATIAL VIEW</p><h2 id="workspace-title">Workspace snapshot</h2></div>
        <span class="live-indicator"><i></i> Illustrative availability</span>
    </div>

    <div class="workspace-grid">
        <article class="map-card">
            <div class="map-toolbar">
                <div class="map-title-group"><span class="map-title-mark" aria-hidden="true"><x-icon name="building" /></span><div><h3>Building overview</h3><p>Illustrative 3D floor · geometry not yet verified</p></div></div>
                <div class="map-filters">
                    <label class="select-wrap"><span class="sr-only">Building</span><select id="building-select" aria-label="Building"><option>Main Building · illustrative</option><option disabled>Other buildings · inventory pending</option></select><span aria-hidden="true">⌄</span></label>
                    <label class="select-wrap select-floor"><span class="sr-only">Floor</span><select id="floor-select" aria-label="Floor"><option value="1" @selected($initialFloor === 1)>1st Floor</option><option value="2" @selected($initialFloor === 2)>2nd Floor</option></select><span aria-hidden="true"><x-icon name="chevron-down" /></span></label>
                    <label class="select-wrap select-status"><span class="sr-only">Filter rooms by status</span><select id="status-select" aria-label="Filter rooms by status"><option value="all">All statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="maintenance">Maintenance</option><option value="unavailable">Unavailable</option></select><span aria-hidden="true">⌄</span></label>
                </div>
            </div>

            <div class="map-stage" id="map-stage">
                <div class="map-caption"><span class="map-caption-kicker">NPC · MAIN BUILDING · ILLUSTRATIVE</span><span class="map-caption-floor" id="map-floor-label">LEVEL 0{{ $initialFloor }}</span></div>
                <canvas id="floor-plan" aria-label="Interactive illustrative 3D floor plan. Drag to rotate, use the controls to zoom, and select a room for details." role="img" tabindex="0"></canvas>
                <div class="map-tooltip" id="map-tooltip" role="status" aria-live="polite" hidden></div>
                <div class="map-controls" aria-label="Floor plan view controls">
                    <x-map-control action="zoom-in" label="Zoom in" icon="plus" />
                    <x-map-control action="zoom-out" label="Zoom out" icon="minus" />
                    <span class="control-rule"></span>
                    <x-map-control action="center" label="Recenter floor plan" icon="center" />
                    <x-map-control action="rotate" label="Rotate floor plan" icon="rotate" />
                    <x-map-control action="fullscreen" label="View floor plan fullscreen" icon="maximize" />
                </div>
                <div class="map-help"><span class="drag-mark" aria-hidden="true">↔</span> Drag to rotate <span class="help-separator">·</span> Scroll to zoom</div>
            </div>

            <div class="map-legend" aria-label="Room status legend">
                <span class="legend-title">ROOM STATUS</span>
                <span class="legend-item"><i class="legend-dot available"></i>Available</span>
                <span class="legend-item"><i class="legend-dot occupied"></i>Occupied</span>
                <span class="legend-item"><i class="legend-dot reserved"></i>Reserved</span>
                <span class="legend-item"><i class="legend-dot maintenance"></i>Maintenance</span>
                <span class="legend-item"><i class="legend-dot unavailable"></i>Unavailable</span>
            </div>
            <p class="map-data-note">Sample room layout for interface review. Replace with verified Navotas Polytechnic College floor plans before publishing availability.</p>
        </article>

        <aside class="snapshot-side" aria-label="Current room status and upcoming reservations">
            <section class="side-panel availability-panel" aria-labelledby="availability-title" aria-busy="false">
                <div class="panel-heading"><div class="panel-heading-title"><span class="panel-heading-mark" aria-hidden="true"><x-icon name="list-checks" /></span><span><p class="eyebrow">RIGHT NOW · SAMPLE</p><h3 id="availability-title">Room availability</h3></span></div><span class="panel-count">{{ count($roomsByFloor[$initialFloor]) }} <small>rooms</small></span></div>
                <p class="room-load-message" id="room-load-message" role="status" hidden></p>
                <div class="room-status-list" id="room-status-list" data-endpoint="{{ route('dashboard.rooms') }}">
                    @foreach ($roomsByFloor[$initialFloor] as $room)
                        <x-room-row :room="$room" :floor="(string) $initialFloor" />
                    @endforeach
                </div>
                <div class="side-panel-foot"><span>Based on sample status</span></div>
            </section>

            <section class="side-panel schedule-panel">
                <div class="panel-heading"><div class="panel-heading-title"><span class="panel-heading-mark upcoming-mark" aria-hidden="true"><x-icon name="calendar" /></span><span><p class="eyebrow">TODAY · SAMPLE</p><h3>Upcoming</h3></span></div><span class="schedule-date">{{ now()->format('M j') }}</span></div>
                <ol class="upcoming-list">
                    <li><span class="schedule-time">10:00</span><span class="schedule-track"><i class="track-dot track-blue"></i></span><span class="schedule-details"><strong>Organization meeting</strong><small>Room 204 · 10:00–11:30</small></span><span class="reservation-state">Approved</span></li>
                    <li><span class="schedule-time">13:30</span><span class="schedule-track"><i class="track-dot track-amber"></i></span><span class="schedule-details"><strong>Workshop preparation</strong><small>Room 202 · 13:30–15:00</small></span><span class="reservation-state is-pending">Pending</span></li>
                    <li><span class="schedule-time">15:00</span><span class="schedule-track"><i class="track-dot track-green"></i></span><span class="schedule-details"><strong>Faculty consultation</strong><small>Room 203 · 15:00–16:00</small></span><span class="reservation-state">Approved</span></li>
                </ol>
                <div class="side-panel-foot"><span>Illustrative schedule preview</span></div>
            </section>

            <section class="selection-card" id="room-detail" aria-live="polite" hidden>
                <div class="selection-top"><span class="eyebrow">SELECTED SPACE</span><button type="button" class="selection-close" id="close-room-detail" aria-label="Close room details">×</button></div>
                <div class="selection-room-id" id="detail-room-id"></div>
                <h3 id="detail-room-name"></h3>
                <div class="detail-status" id="detail-status"></div>
                <dl class="room-facts"><div><dt>Capacity</dt><dd id="detail-capacity"></dd></div><div><dt>Equipment</dt><dd id="detail-equipment"></dd></div><div><dt>Next availability</dt><dd id="detail-next"></dd></div></dl>
                <a class="secondary-button" href="{{ route('reservations') }}">Review reservation steps</a>
            </section>
        </aside>
    </div>

    {{ $slot }}
</section>
