<x-layouts.app title="CVR" page-title="Equipment Requests" current-page="equipment-requests">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Equipment Requests" description="Request equipment for an approved room reservation and follow its return status." />

        <section class="content-panel" aria-labelledby="request-status-title">
            <div class="content-panel-heading"><div><p class="eyebrow">REQUEST LIFECYCLE</p><h2 id="request-status-title">{{ $isEquipmentReviewer ? 'Reservation requests' : 'Your equipment requests' }}</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            <div class="status-lifecycle" aria-label="Equipment request statuses">
                <span class="availability-chip is-pending">Pending</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip is-approved">Approved</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Released</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Returned</span>
                <span class="availability-chip is-rejected">Rejected</span>
            </div>
            @if ($canSubmitEquipmentRequests)
                @if ($reservations->isNotEmpty() && $equipment->isNotEmpty())
                    <form class="stack-form equipment-request-form" data-equipment-request-form data-endpoint="{{ route('api.equipment-requests.store') }}" data-item-endpoint="{{ url('/api/equipment-requests') }}">
                        @csrf
                        <div class="form-grid-two">
                            <label class="filter-field"><span>Approved reservation</span><select name="reservation_id" required><option value="">Choose a reservation</option>@foreach ($reservations as $reservation)<option value="{{ $reservation->id }}">{{ $reservation->room->code }} · {{ $reservation->purpose }} · {{ $reservation->starts_at->format('M j, g:i A') }}</option>@endforeach</select></label>
                            <label class="filter-field"><span>Equipment</span><select name="equipment_id" required><option value="">Choose equipment</option>@foreach ($equipment as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->available_quantity }} available</option>@endforeach</select></label>
                            <label class="filter-field"><span>Quantity</span><input name="quantity" type="number" min="1" max="1000000" value="1" required></label>
                        </div>
                        <label class="filter-field"><span>Notes (optional)</span><textarea name="notes" rows="2" maxlength="2000"></textarea></label>
                        <p class="form-message" data-equipment-request-message role="status" aria-live="polite"></p>
                        <div class="reservation-actions"><button class="primary-button" type="submit">Submit equipment request</button></div>
                    </form>
                @else
                    <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>Equipment requests require an upcoming approved room reservation and active inventory records.</p></div>
                @endif
            @endif
            <p class="directory-message" data-equipment-request-list-message role="status" aria-live="polite">Loading equipment requests…</p>
            <div class="request-list equipment-request-list" data-equipment-request-list data-endpoint="{{ route('api.equipment-requests.index') }}" data-item-endpoint="{{ url('/api/equipment-requests') }}" data-reviewer="{{ $isEquipmentReviewer ? 'true' : 'false' }}">
                @foreach ($requests as $request)
                    <article class="request-row"><strong>{{ $request->equipment->name }}</strong><span>{{ $request->quantity }} requested · {{ ucfirst($request->status) }}</span></article>
                @endforeach
            </div>
        </section>
    </main>
</x-layouts.app>
