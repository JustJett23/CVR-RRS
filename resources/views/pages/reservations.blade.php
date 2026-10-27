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
                <form class="reservation-form stack-form" data-reservation-form data-default-duration="{{ auth()->user()->preferences['default_reservation_duration'] ?? 60 }}" data-endpoint="{{ route('api.reservations.store') }}">
                    @csrf
                    @if ($selectedRoom)
                        <input type="hidden" name="room_id" value="{{ $selectedRoom->id }}">
                        <section class="selected-room-summary" aria-label="Selected room">
                            <p class="eyebrow">ROOM SELECTED</p>
                            <h3>{{ $selectedRoom->code }} · {{ $selectedRoom->name }}</h3>
                            <p>{{ $selectedRoom->floor->building->name }} · {{ $selectedRoom->floor->name }} · {{ $selectedRoom->capacity }} seats</p>
                            <p class="selected-room-start-note">Suggested start time is one hour from now. You can change it before submitting.</p>
                        </section>
                    @else
                        <div class="form-grid-two">
                            <label class="filter-field"><span>Room</span><select name="room_id" required><option value="">Choose a room</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->code }} · {{ $room->name }} · Floor {{ $room->floor->number }}</option>@endforeach</select></label>
                        </div>
                    @endif
                    <div class="form-grid-two">
                        <label class="filter-field"><span>Purpose</span><input name="purpose" maxlength="255" required></label>
                        <label class="filter-field"><span>Starts</span><input type="datetime-local" name="starts_at" value="{{ old('starts_at', $defaultStart) }}" required></label>
                        <label class="filter-field"><span>Duration</span><select name="duration_minutes" required><option value="60" @selected((int) (auth()->user()->preferences['default_reservation_duration'] ?? 60) === 60)>1 hour</option><option value="120" @selected((int) (auth()->user()->preferences['default_reservation_duration'] ?? 60) === 120)>2 hours</option><option value="180" @selected((int) (auth()->user()->preferences['default_reservation_duration'] ?? 60) === 180)>3 hours</option><option value="240" @selected((int) (auth()->user()->preferences['default_reservation_duration'] ?? 60) === 240)>4 hours</option></select></label>
                        <input type="hidden" name="ends_at" required>
                    </div>
                    @unless ($selectedRoom)
                        <label class="filter-field"><span>Organization (optional)</span><input name="organization_name" maxlength="255" value="{{ auth()->user()->preferences['default_organization_name'] ?? '' }}"></label>
                        <label class="filter-field"><span>Notes (optional)</span><textarea name="notes" rows="3" maxlength="2000"></textarea></label>
                    @endunless
                    <p class="form-message" data-reservation-message role="status" aria-live="polite"></p>
                    <button class="primary-button" type="submit">Submit reservation request</button>
                </form>
            @else
                <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>Sign in to submit a request against the live room inventory.</p></div>
                <div class="page-actions"><a class="primary-button" href="{{ route('login') }}">Sign in to reserve</a><a class="text-link" href="{{ route('rooms') }}">Browse rooms</a></div>
            @endauth
        </section>

        @if (session('status'))
            <div class="notice-panel reservation-feedback" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->has('reservation'))
            <div class="notice-panel reservation-feedback" role="alert">{{ $errors->first('reservation') }}</div>
        @endif

        <section class="content-panel reservation-records" aria-labelledby="reservation-records-title">
            <div class="content-panel-heading">
                <div><p class="eyebrow">{{ $isStaff ? 'REVIEW QUEUE' : 'YOUR REQUESTS' }}</p><h2 id="reservation-records-title">{{ $isStaff ? 'Reservation requests' : 'My reservations' }}</h2></div>
                <span class="live-indicator"><i></i> Live database</span>
            </div>
            @if ($reservations->isEmpty())
                <x-empty-state icon="calendar" eyebrow="NO RESERVATIONS" title="No reservation requests yet" description="Submitted requests and their review status will appear here." />
            @else
                <div class="reservation-record-list">
                    @foreach ($reservations as $reservation)
                        <article class="reservation-record">
                            <div class="reservation-record-heading">
                                <div><h3>{{ $reservation->room->code }} · {{ $reservation->room->name }}</h3><p>{{ $reservation->room->floor->building->name }} · {{ $reservation->room->floor->name }}</p></div>
                                <span class="availability-chip {{ $reservation->status === 'pending' ? 'is-pending' : ($reservation->status === 'approved' ? 'is-approved' : ($reservation->status === 'rejected' ? 'is-rejected' : '')) }}">{{ ucfirst($reservation->status) }}</span>
                            </div>
                            <p class="reservation-record-purpose">{{ $reservation->purpose }}</p>
                            <p class="reservation-record-time">{{ $reservation->starts_at->format('M j, Y · g:i A') }} – {{ $reservation->ends_at->format('g:i A') }}</p>
                            @if ($isStaff)
                                <p class="reservation-record-requester">Requested by {{ $reservation->user->name }}@if ($reservation->organization_name) · {{ $reservation->organization_name }}@endif</p>
                            @endif
                            @if ($reservation->status === 'pending')
                                <div class="reservation-record-actions">
                                    @if ($isStaff)
                                        <form method="POST" action="{{ route('reservations.approve', $reservation) }}" data-loading-form data-loading-message="Rechecking availability and approving…">@csrf<button class="primary-button" type="submit">Approve</button></form>
                                        <form method="POST" action="{{ route('reservations.reject', $reservation) }}" data-loading-form data-loading-message="Declining reservation…">@csrf<button class="secondary-button" type="submit">Decline</button></form>
                                    @elseif ($reservation->user_id === auth()->id())
                                        <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" data-loading-form data-loading-message="Cancelling reservation…">@csrf<button class="secondary-button" type="submit">Cancel request</button></form>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</x-layouts.app>
