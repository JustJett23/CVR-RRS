<x-layouts.app title="CVR" page-title="Insights & Reports" current-page="reports">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="INSIGHTS" title="Insights & Reports" description="Review reservation activity, room usage, and equipment demand from campus records." />

        <section class="content-panel report-filter-panel" aria-label="Report date range">
            <form method="GET" action="{{ route('reports') }}" class="filter-row">
                <label class="filter-field">
                    <span>From</span>
                    <input type="date" name="start_date" value="{{ $startDate->toDateString() }}" required>
                </label>
                <label class="filter-field">
                    <span>Through</span>
                    <input type="date" name="end_date" value="{{ $endDate->toDateString() }}" required>
                </label>
                <button class="primary-button" type="submit">Apply dates</button>
            </form>
            @if ($errors->any())
                <p class="form-message" role="alert">{{ $errors->first() }}</p>
            @endif
        </section>

        <section class="metrics-grid report-metrics" aria-label="Report summary">
            <x-metric-card label="Reservation requests" :value="number_format($metrics['reservationCount'])" caption="All statuses" context="DATE RANGE" icon="calendar" />
            <x-metric-card label="Approved bookings" :value="number_format($metrics['approvedCount'])" caption="Confirmed room use" context="DATE RANGE" icon="calendar-check" />
            <x-metric-card label="Booked hours" :value="$metrics['bookedHours']" caption="Approved reservations" context="DATE RANGE" icon="clock" />
            <x-metric-card label="Equipment borrowed" :value="number_format($metrics['equipmentBorrowedUnits'])" caption="Checked out in selected range" context="DATE RANGE" icon="package" />
        </section>

        <div class="report-data-grid">
            <section class="content-panel" aria-labelledby="room-usage-title">
                <div class="content-panel-heading">
                    <div><p class="eyebrow">APPROVED RESERVATIONS</p><h2 id="room-usage-title">Room usage</h2></div>
                    <span class="live-indicator"><i></i> Live database</span>
                </div>
                @if ($roomUsage->isEmpty())
                    <p class="report-empty-state">No approved reservations start within this date range.</p>
                @else
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead><tr><th scope="col">Room</th><th scope="col">Approved bookings</th><th scope="col">Booked hours</th></tr></thead>
                            <tbody>
                                @foreach ($roomUsage as $room)
                                    <tr>
                                        <th scope="row"><strong>{{ $room->code }} · {{ $room->name }}</strong><span>{{ $room->building_name }} · {{ $room->floor_name }}</span></th>
                                        <td>{{ number_format($room->reservations_count) }}</td>
                                        <td>{{ number_format($room->booked_minutes / 60, 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="availability-note">Showing the ten rooms with the most approved booking time in this range.</p>
                @endif
            </section>

            <section class="content-panel" aria-labelledby="equipment-demand-title">
                <div class="content-panel-heading">
                    <div><p class="eyebrow">LINKED EQUIPMENT REQUESTS</p><h2 id="equipment-demand-title">Equipment demand</h2></div>
                    <span class="live-indicator"><i></i> Live database</span>
                </div>
                @if ($equipmentDemand->isEmpty())
                    <p class="report-empty-state">No equipment requests are linked to reservations in this date range.</p>
                @else
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead><tr><th scope="col">Equipment</th><th scope="col">Requests</th><th scope="col">Units</th></tr></thead>
                            <tbody>
                                @foreach ($equipmentDemand as $item)
                                    <tr>
                                        <th scope="row"><strong>{{ $item->name }}</strong><span>{{ $item->category ?: 'Other equipment' }}</span></th>
                                        <td>{{ number_format($item->requests_count) }}</td>
                                        <td>{{ number_format($item->units_requested) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="report-status-breakdown">
                    <h3>Requests by status</h3>
                    @forelse ($equipmentByStatus as $status)
                        <div><span>{{ $status->status === 'released' ? 'Borrowed' : ucfirst($status->status) }}</span><strong>{{ number_format($status->requests_count) }} requests · {{ number_format($status->units_requested) }} units</strong></div>
                    @empty
                        <p class="report-empty-state">No request statuses to summarize.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="sample-notice" role="note"><span class="notice-mark" aria-hidden="true">i</span><span>Room usage counts approved reservations by their start date. Booked hours use each reservation’s full scheduled duration.</span></div>
    </main>
</x-layouts.app>
