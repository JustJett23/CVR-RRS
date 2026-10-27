<x-layouts.app title="CVR" page-title="Schedule Timeline" current-page="schedule">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Schedule Timeline" description="Review today’s reservations by floor from the same data that powers the 3D floor plan." :action-label="auth()->user()?->role === 'student' ? null : 'Reserve a room'" :action-href="auth()->user()?->role === 'student' ? null : route('reservations')" />

        <section class="content-panel" aria-labelledby="schedule-title">
            <div class="content-panel-heading"><div><p class="eyebrow">CAMPUS SCHEDULE</p><h2 id="schedule-title">Daily room timeline</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            <div class="filter-row" id="schedule-filters" data-endpoint="{{ route('dashboard.floor-plan-data') }}">
                <label class="filter-field"><span>Date</span><input id="schedule-date-input" type="date" value="{{ today()->toDateString() }}"></label>
                <label class="filter-field"><span>Floor</span><select id="schedule-floor"><option value="1">2nd Floor</option><option value="2">3rd Floor</option><option value="3">4th Floor</option></select></label>
                <label class="filter-field"><span>Room type</span><select id="schedule-type"><option value="">All room types</option>@foreach ($roomTypes as $roomType)<option value="{{ $roomType }}">{{ $roomType }}</option>@endforeach</select></label>
            </div>
            <p class="availability-note" id="schedule-message" role="status" aria-live="polite">Loading reservations…</p>
            <ol class="upcoming-list schedule-page-list" id="schedule-list" aria-live="polite"></ol>
        </section>
    </main>
</x-layouts.app>
