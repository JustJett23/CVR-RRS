<x-layouts.app title="CVR" page-title="Reservations" current-page="reservations">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="PLANNING" title="Reservations" description="Submit a room request against the live room schedule. The server checks conflicts before saving it." />

        <section class="content-panel reservation-guide" aria-labelledby="reservation-guide-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">REQUEST JOURNEY</p><h2 id="reservation-guide-title">From schedule to confirmation</h2></div>
                <span class="live-indicator"><i></i> Database workflow</span>
            </div>
            <ol class="workflow-list">
                <li><span class="workflow-number">01</span><div><h3>Choose a date</h3><p>Select the day the room is needed.</p></div><span class="workflow-state">Database check</span></li>
                <li><span class="workflow-number">02</span><div><h3>Set the time</h3><p>Provide start and end times for the request.</p></div><span class="workflow-state">Database check</span></li>
                <li><span class="workflow-number">03</span><div><h3>Select the floor</h3><p>Narrow the NPC Main Building spaces to the area that fits.</p></div><span class="workflow-state">Live inventory</span></li>
                <li><span class="workflow-number">04</span><div><h3>Choose an available room</h3><p>Availability must be checked against current reservations and maintenance periods.</p></div><span class="workflow-state">Live schedule required</span></li>
                <li><span class="workflow-number">05</span><div><h3>Add request details</h3><p>Include the purpose, organization, expected attendees, and equipment needs.</p></div><span class="workflow-state">Account access required</span></li>
                <li><span class="workflow-number">06</span><div><h3>Review the request</h3><p>Check room details, time, and any equipment before submitting.</p></div><span class="workflow-state">Ready to submit</span></li>
                <li><span class="workflow-number">07</span><div><h3>Submit for review</h3><p>The server rechecks availability and prevents conflicting requests.</p></div><span class="workflow-state">Server validated</span></li>
            </ol>
            @auth
                <form class="reservation-form stack-form" data-reservation-form data-endpoint="{{ route('api.reservations.store') }}">
                    @csrf
                    <div class="form-grid-two">
                        <label class="filter-field"><span>Room</span><select name="room_id" required><option value="">Choose a room</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->code }} · {{ $room->name }} · Floor {{ $room->floor->number }}</option>@endforeach</select></label>
                        <label class="filter-field"><span>Purpose</span><input name="purpose" maxlength="255" required></label>
                        <label class="filter-field"><span>Starts</span><input type="datetime-local" name="starts_at" required></label>
                        <label class="filter-field"><span>Ends</span><input type="datetime-local" name="ends_at" required></label>
                    </div>
                    <label class="filter-field"><span>Organization (optional)</span><input name="organization_name" maxlength="255"></label>
                    <label class="filter-field"><span>Notes (optional)</span><textarea name="notes" rows="3" maxlength="2000"></textarea></label>
                    <p class="form-message" data-reservation-message role="status" aria-live="polite"></p>
                    <button class="primary-button" type="submit">Submit reservation request</button>
                </form>
            @else
                <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>Sign in to submit a request against the live room inventory.</p></div>
                <div class="page-actions"><a class="primary-button" href="{{ route('login') }}">Sign in to reserve</a><a class="text-link" href="{{ route('rooms') }}">Browse rooms</a></div>
            @endauth
        </section>
    </main>
</x-layouts.app>
