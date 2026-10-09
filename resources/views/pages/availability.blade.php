<x-layouts.app title="CVR" page-title="Room Availability" current-page="availability">
    <main class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Room Availability" description="Live openings need an approved room inventory and reservation schedule." action-label="Browse sample rooms" :action-href="route('rooms')" />

        <section class="content-panel" aria-labelledby="availability-search-title">
            <div class="content-panel-heading"><div><p class="eyebrow">AVAILABILITY CHECK</p><h2 id="availability-search-title">Find a space</h2></div><span class="sample-tag">Live data not connected</span></div>
            <div class="filter-row availability-filters" aria-describedby="availability-disabled-note">
                <label class="filter-field"><span>Date</span><input type="date" disabled aria-disabled="true"></label>
                <label class="filter-field"><span>Start time</span><input type="time" disabled aria-disabled="true"></label>
                <label class="filter-field"><span>End time</span><input type="time" disabled aria-disabled="true"></label>
                <label class="filter-field"><span>Building</span><select disabled aria-disabled="true"><option>Choose building</option></select></label>
                <label class="filter-field"><span>Floor</span><select disabled aria-disabled="true"><option>Choose floor</option></select></label>
                <label class="filter-field"><span>Room type</span><select disabled aria-disabled="true"><option>Choose room type</option></select></label>
            </div>
            <p class="availability-note" id="availability-disabled-note"><x-icon name="clock" /> Filters will activate when official room inventory and schedules are connected. Current sample statuses do not confirm a reservable time.</p>
            <x-empty-state class="availability-empty" icon="calendar" eyebrow="NO LIVE RESULTS" title="Availability is not connected yet" description="The preview shows illustrative room statuses only. Live results require server-checked reservation windows and maintenance blocks." action-label="Explore the floor plan" :action-href="route('floor-plan')" />
        </section>
    </main>
</x-layouts.app>
