<x-layouts.app title="CVR" page-title="Insights & Reports" current-page="reports">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="INSIGHTS" title="Insights & Reports" description="Live summaries from room, reservation, and equipment records." />

        <form class="filter-row" method="get" action="{{ route('reports') }}" aria-label="Report date range">
            <label for="report-days">Date range</label>
            <select id="report-days" name="days" onchange="this.form.submit()">
                @foreach([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $value => $label)
                    <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button class="secondary-button" type="submit">Update report</button></noscript>
        </form>

        <section class="metrics-grid" aria-label="Report totals">
            @foreach(['rooms' => 'Rooms', 'reservations' => 'Reservations', 'approved' => 'Approved', 'pending' => 'Pending', 'cancelled' => 'Cancelled', 'equipment_requests' => 'Equipment requests'] as $key => $label)
                <article class="metric-card"><span>{{ $label }}</span><strong>{{ number_format($totals[$key]) }}</strong><small>{{ $key === 'rooms' ? 'Current inventory' : 'Created in selected period' }}</small></article>
            @endforeach
        </section>

        <section class="report-grid" aria-label="Reservation reports">
            <article class="content-panel report-detail"><h2>Reservations by status</h2>
                @forelse($byStatus as $row)<p><strong>{{ str($row->status)->headline() }}</strong><span>{{ number_format($row->total) }}</span></p>@empty<p>No reservations in this period.</p>@endforelse
            </article>
            <article class="content-panel report-detail"><h2>Most requested rooms</h2>
                @forelse($byRoom as $row)<p><strong>{{ $row->room_name }}</strong><span>{{ number_format($row->total) }}</span></p>@empty<p>No room requests in this period.</p>@endforelse
            </article>
            <article class="content-panel report-detail"><h2>Reservation trend by month</h2>
                @forelse($byMonth as $row)<p><strong>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['month'])->format('F Y') }}</strong><span>{{ number_format($row['total']) }}</span></p>@empty<p>No reservations in this period.</p>@endforelse
            </article>
            <article class="content-panel report-detail"><h2>Peak booking hours</h2>
                @forelse($peakTimes as $row)<p><strong>{{ $row['hour'] }}</strong><span>{{ number_format($row['total']) }}</span></p>@empty<p>No reservations in this period.</p>@endforelse
            </article>
            <article class="content-panel report-detail"><h2>Organization activity</h2>
                @forelse($byOrganization as $row)<p><strong>{{ $row['organization'] }}</strong><span>{{ number_format($row['total']) }}</span></p>@empty<p>No organization names were recorded in this period.</p>@endforelse
            </article>
            <article class="content-panel report-detail"><h2>Equipment requests by item</h2>
                @forelse($byEquipment as $row)<p><strong>{{ $row->equipment_name }}</strong><span>{{ number_format($row->total) }}</span></p>@empty<p>No equipment requests in this period.</p>@endforelse
            </article>
        </section>
    </main>
</x-layouts.app>
