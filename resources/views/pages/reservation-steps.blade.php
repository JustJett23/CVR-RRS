<x-layouts.app title="CVR" page-title="Reservation Steps" current-page="reservations">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Reservation Steps" description="Use the live room directory and schedule to prepare a request." :action-label="auth()->user()?->role === 'student' ? null : 'Reserve a room'" :action-href="auth()->user()?->role === 'student' ? null : route('reservations')" />
        <section class="content-panel" aria-labelledby="steps-title">
            <div class="content-panel-heading"><div><p class="eyebrow">GUIDE</p><h2 id="steps-title">From room search to submission</h2></div><span class="live-indicator"><i></i> Database workflow</span></div>
            <ol class="workflow-list">
                <li><span class="workflow-number">01</span><div><h3>Check the schedule</h3><p>Review room availability and upcoming reservations.</p></div></li>
                <li><span class="workflow-number">02</span><div><h3>Choose a room and time</h3><p>Select a room that is available for the complete requested window.</p></div></li>
                <li><span class="workflow-number">03</span><div><h3>Submit the request</h3><p>Provide the purpose and dates. The server checks conflicts before saving.</p></div></li>
                <li><span class="workflow-number">04</span><div><h3>Track the result</h3><p>Follow your request status from the reservations page.</p></div></li>
            </ol>
        </section>
    </main>
</x-layouts.app>
