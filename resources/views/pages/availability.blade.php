<x-layouts.app title="CVR" page-title="Room Availability" current-page="availability">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Room Availability" description="Check live room status against reservations and maintenance windows." action-label="Schedule Timeline" :action-href="route('schedule')" />

        <section class="content-panel" aria-labelledby="availability-search-title">
            <div class="content-panel-heading"><div><p class="eyebrow">AVAILABILITY CHECK</p><h2 id="availability-search-title">Find a space</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            <div class="filter-row availability-filters" id="availability-filters" data-endpoint="{{ route('dashboard.floor-plan-data') }}">
                <label class="filter-field"><span>Date</span><input id="availability-date" type="date" value="{{ today()->toDateString() }}"></label>
                <label class="filter-field"><span>Start time</span><input id="availability-start" type="time"></label>
                <label class="filter-field"><span>End time</span><input id="availability-end" type="time"></label>
                <label class="filter-field"><span>Floor</span><select id="availability-floor"><option value="1">2nd Floor</option><option value="2">3rd Floor</option><option value="3">4th Floor</option></select></label>
            </div>
            <p class="availability-note" id="availability-message" role="status" aria-live="polite"><x-icon name="clock" /> Availability is calculated from persisted room, reservation, and maintenance records.</p>
            <div class="room-directory-grid" id="availability-results" aria-live="polite" aria-busy="false"></div>
        </section>
    </main>
</x-layouts.app>
