<x-layouts.app title="CVR" page-title="{{ $isStaff ? 'Equipment Loan Review' : 'Borrow Equipment' }}" current-page="equipment-requests">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="{{ $isStaff ? 'Equipment Loan Review' : 'Borrow Equipment' }}" description="{{ $isStaff ? 'Review requests, prepare approved loans, and record equipment returns.' : 'Choose available equipment for one of your upcoming approved room reservations.' }}" />

        @if (session('status'))
            <div class="notice-panel reservation-feedback" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice-panel reservation-feedback" role="alert">{{ $errors->first() }}</div>
        @endif

        @unless ($isStaff)
            <section class="equipment-catalog-grid" aria-label="Equipment available to borrow">
                @forelse ($equipment as $item)
                    @php($equipmentIcon = match ($item->category) { 'Presentation' => 'projector', 'Cables' => 'cable', 'Power' => 'plug', default => 'package' })
                    <article class="equipment-card">
                        <div class="equipment-card-art" aria-hidden="true"><x-icon :name="$equipmentIcon" /></div>
                        <div class="equipment-card-body">
                            <p class="eyebrow">{{ $item->category ?: 'CAMPUS EQUIPMENT' }}</p>
                            <h2>{{ $item->name }}</h2>
                            @if ($item->description)<p class="equipment-card-description">{{ $item->description }}</p>@endif
                            <dl class="equipment-card-facts">
                                <div><dt>Availability</dt><dd class="{{ $item->available_quantity > 0 ? 'is-available' : 'is-unavailable' }}">{{ $item->available_quantity > 0 ? 'Available' : 'Out of stock' }}</dd></div>
                                <div><dt>How many</dt><dd>{{ $item->available_quantity }} available · {{ $item->quantity }} total</dd></div>
                            </dl>
                        </div>
                        <footer class="equipment-card-footer">
                            @if ($item->available_quantity > 0 && $reservations->isNotEmpty())
                                <button class="primary-button equipment-borrow-button" type="button" data-equipment-form-toggle data-equipment-id="{{ $item->id }}" aria-expanded="{{ $selectedEquipmentId === $item->id ? 'true' : 'false' }}" aria-controls="equipment-request-form-panel">Borrow {{ $item->name }}</button>
                            @else
                                <button class="secondary-button equipment-borrow-button is-disabled" type="button" disabled>Borrow {{ $item->name }}</button>
                            @endif
                        </footer>
                    </article>
                @empty
                    <div class="content-panel equipment-catalog-empty"><x-empty-state icon="package" eyebrow="NO INVENTORY" title="No equipment listed" description="Available campus equipment will appear here when it is added to the inventory." /></div>
                @endforelse
            </section>

            @if ($reservations->isEmpty())
                <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>An upcoming approved room reservation is required before you can borrow equipment.</p></div>
            @endif

            @if ($reservations->isNotEmpty() && $equipment->isNotEmpty())
                <section class="content-panel equipment-request-form-panel" id="equipment-request-form-panel" aria-labelledby="new-equipment-request-title" @if (! $selectedEquipmentId) hidden @endif>
                    <div class="content-panel-heading"><div><p class="eyebrow">BORROW REQUEST</p><h2 id="new-equipment-request-title">Link equipment to a room reservation</h2></div><div class="equipment-form-heading-actions"><span class="live-indicator"><i></i> Live stock</span><button class="secondary-button" type="button" data-equipment-form-toggle data-equipment-form-close aria-expanded="{{ $selectedEquipmentId ? 'true' : 'false' }}" aria-controls="equipment-request-form-panel">Close request form</button></div></div>
                    <form method="POST" action="{{ route('equipment-requests.store') }}" class="filter-row equipment-request-form">
                        @csrf
                        <label class="filter-field"><span>Approved reservation</span><select name="reservation_id" required><option value="">Select a reservation</option>@foreach ($reservations as $reservation)<option value="{{ $reservation->id }}" @selected(old('reservation_id') == $reservation->id)>{{ $reservation->room->code }} · {{ $reservation->room->name }} · {{ $reservation->starts_at->format('M j, Y g:i A') }}–{{ $reservation->ends_at->format('g:i A') }}</option>@endforeach</select></label>
                        <label class="filter-field"><span>Equipment</span><select name="equipment_id" required><option value="">Select equipment</option>@foreach ($equipment as $item)<option value="{{ $item->id }}" @selected(old('equipment_id', $selectedEquipmentId) == $item->id) @disabled($item->available_quantity < 1)>{{ $item->name }} · {{ $item->available_quantity }} available</option>@endforeach</select></label>
                        <label class="filter-field"><span>Quantity</span><input type="number" name="quantity" min="1" max="1000" value="{{ old('quantity', 1) }}" required></label>
                        <label class="filter-field equipment-notes-field"><span>Notes (optional)</span><input name="notes" maxlength="1000" value="{{ old('notes') }}" placeholder="Purpose or delivery details"></label>
                        <button class="primary-button" type="submit">Submit borrow request</button>
                    </form>
                </section>
            @endif
        @endunless

        <section class="content-panel equipment-request-records" aria-labelledby="request-status-title">
            <div class="content-panel-heading"><div><p class="eyebrow">{{ $isStaff ? 'REVIEW QUEUE' : 'YOUR LOANS' }}</p><h2 id="request-status-title">{{ $isStaff ? 'Equipment requests' : 'Your equipment requests' }}</h2></div><span class="live-indicator"><i></i> {{ number_format($requests->total()) }} records</span></div>
            @if ($requests->isEmpty())
                <x-empty-state icon="list-checks" eyebrow="NO REQUESTS" title="No equipment requests" description="{{ $isStaff ? 'Borrow requests will appear here for review.' : 'Your equipment requests and loan statuses will appear here.' }}" />
            @else
                <div class="equipment-request-list">
                    @foreach ($requests as $equipmentRequest)
                        @php($reservation = $equipmentRequest->reservation)
                        @php($borrowDurationHours = $reservation->starts_at->diffInMinutes($reservation->ends_at) / 60)
                        @php($statusLabel = $equipmentRequest->status === 'released' ? 'Borrowed' : ucfirst($equipmentRequest->status))
                        @php($equipmentIcon = match ($equipmentRequest->equipment->category) { 'Presentation' => 'projector', 'Cables' => 'cable', 'Power' => 'plug', default => 'package' })
                        <article class="equipment-request-record">
                            <div class="equipment-request-art" aria-hidden="true"><x-icon :name="$equipmentIcon" /></div>
                            <div class="equipment-request-record-content">
                                <div class="equipment-request-record-heading">
                                    <div><p class="eyebrow">{{ $equipmentRequest->equipment->category ?: 'CAMPUS EQUIPMENT' }}</p><h3>{{ $equipmentRequest->equipment->name }} <span>× {{ $equipmentRequest->quantity }}</span></h3></div>
                                    <span class="availability-chip {{ $equipmentRequest->status === 'pending' ? 'is-pending' : (in_array($equipmentRequest->status, ['approved', 'released', 'returned']) ? 'is-approved' : 'is-rejected') }}">{{ $statusLabel }}</span>
                                </div>
                                <dl class="equipment-loan-details">
                                    <div><dt>Borrower</dt><dd>{{ $equipmentRequest->requester->name }}</dd></div>
                                    <div><dt>Room</dt><dd>{{ $reservation->room->code }} · {{ $reservation->room->name }}</dd></div>
                                    <div><dt>Borrow period</dt><dd>{{ $reservation->starts_at->format('M j, Y · g:i A') }}–{{ $reservation->ends_at->format('g:i A') }} · {{ number_format($borrowDurationHours, 1) }} hours</dd></div>
                                </dl>
                                @if ($equipmentRequest->notes)<p class="equipment-request-notes">{{ $equipmentRequest->notes }}</p>@endif
                                @if ($isStaff && $equipmentRequest->status === 'approved')
                                    <p class="equipment-ready-note">Ready for {{ $equipmentRequest->requester->name }} to borrow.</p>
                                @elseif ($isStaff && $equipmentRequest->status === 'released')
                                    <p class="equipment-ready-note is-borrowed">Currently borrowed by {{ $equipmentRequest->requester->name }}.</p>
                                @endif
                                @if ($isStaff)
                                    <div class="reservation-record-actions">
                                        @if ($equipmentRequest->status === 'pending')
                                            <form method="POST" action="{{ route('equipment-requests.approve', $equipmentRequest) }}">@csrf<button class="primary-button" type="submit">Approve</button></form>
                                            <form method="POST" action="{{ route('equipment-requests.reject', $equipmentRequest) }}">@csrf<button class="secondary-button" type="submit">Reject</button></form>
                                        @elseif ($equipmentRequest->status === 'approved')
                                            <form method="POST" action="{{ route('equipment-requests.release', $equipmentRequest) }}">@csrf<button class="primary-button" type="submit">Mark borrowed</button></form>
                                        @elseif ($equipmentRequest->status === 'released')
                                            <form method="POST" action="{{ route('equipment-requests.return', $equipmentRequest) }}">@csrf<button class="primary-button" type="submit">Mark returned</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="activity-pagination">{{ $requests->links() }}</div>
            @endif
        </section>
    </main>
</x-layouts.app>
