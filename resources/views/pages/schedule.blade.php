<x-layouts.app title="CVR" page-title="Schedule Timeline" current-page="schedule">
    <main class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Schedule Timeline" description="A room-by-room timeline will show bookings, free periods, and maintenance windows." />

        <section class="content-panel" aria-labelledby="schedule-title">
            <div class="content-panel-heading"><div><p class="eyebrow">CAMPUS SCHEDULE</p><h2 id="schedule-title">Daily room timeline</h2></div><span class="sample-tag">Schedule source not connected</span></div>
            <div class="timeline-shell" aria-hidden="true">
                <div class="timeline-hours"><span>08:00</span><span>10:00</span><span>12:00</span><span>14:00</span><span>16:00</span><span>18:00</span></div>
                <div class="timeline-track"><span></span><span></span><span></span><span></span><span></span></div>
            </div>
            <x-empty-state class="timeline-empty" icon="calendar" eyebrow="NO SCHEDULE DATA" title="No verified bookings to display" description="This timeline will populate when approved reservations and room schedules are connected. Sample events are intentionally omitted here." action-label="Review reservation steps" :action-href="route('reservations')" />
        </section>
    </main>
</x-layouts.app>
