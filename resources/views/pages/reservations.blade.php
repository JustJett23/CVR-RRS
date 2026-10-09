<x-layouts.app title="CVR" page-title="Reservations" current-page="reservations">
    <main class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Reservations" description="Follow the booking journey. Reservation submission will open when verified room schedules and account access are connected." />

        <section class="content-panel reservation-guide" aria-labelledby="reservation-guide-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">REQUEST JOURNEY</p><h2 id="reservation-guide-title">From schedule to confirmation</h2></div>
                <span class="availability-chip is-pending">Submission unavailable</span>
            </div>
            <ol class="workflow-list">
                <li><span class="workflow-number">01</span><div><h3>Choose a date</h3><p>Select the day the room is needed.</p></div><span class="workflow-state">Ready for integration</span></li>
                <li><span class="workflow-number">02</span><div><h3>Set the time</h3><p>Provide start and end times for the request.</p></div><span class="workflow-state">Ready for integration</span></li>
                <li><span class="workflow-number">03</span><div><h3>Select a building and floor</h3><p>Narrow the campus spaces to the area that fits.</p></div><span class="workflow-state">Inventory required</span></li>
                <li><span class="workflow-number">04</span><div><h3>Choose an available room</h3><p>Availability must be checked against current reservations and maintenance periods.</p></div><span class="workflow-state">Live schedule required</span></li>
                <li><span class="workflow-number">05</span><div><h3>Add request details</h3><p>Include the purpose, organization, expected attendees, and equipment needs.</p></div><span class="workflow-state">Account access required</span></li>
                <li><span class="workflow-number">06</span><div><h3>Review the request</h3><p>Check room details, time, and any equipment before submitting.</p></div><span class="workflow-state">Ready for integration</span></li>
                <li><span class="workflow-number">07</span><div><h3>Submit for review</h3><p>The server must recheck availability and prevent conflicting requests.</p></div><span class="workflow-state">Not connected</span></li>
            </ol>
            <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>Booking is not enabled in this preview. No request will be recorded until authentication, official room data, and server-side conflict checks are implemented.</p></div>
            <div class="page-actions"><a class="secondary-button" href="{{ route('rooms') }}">Browse sample rooms</a><a class="text-link" href="{{ route('availability') }}">View availability status</a></div>
        </section>
    </main>
</x-layouts.app>
