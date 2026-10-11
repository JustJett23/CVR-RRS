<x-layouts.app title="CVR" page-title="Equipment Requests" current-page="equipment-requests">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Equipment Requests" description="Request equipment for an approved room reservation and follow its return status." />

        <section class="content-panel" aria-labelledby="request-status-title">
            <div class="content-panel-heading"><div><p class="eyebrow">REQUEST LIFECYCLE</p><h2 id="request-status-title">From request to return</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            <div class="status-lifecycle" aria-label="Equipment request statuses">
                <span class="availability-chip is-pending">Pending</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip is-approved">Approved</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Released</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Returned</span>
                <span class="availability-chip is-rejected">Rejected</span>
            </div>
            @if ($requests->isEmpty())
                <x-empty-state icon="list-checks" eyebrow="NO REQUEST RECORDS" title="No equipment requests" description="Your equipment requests will appear here after they are submitted with a reservation." action-label="Browse equipment" :action-href="route('equipment')" />
            @else
                <div class="request-list">@foreach ($requests as $request)<article class="request-row"><strong>{{ $request->equipment->name }}</strong><span>{{ $request->quantity }} requested · {{ ucfirst($request->status) }}</span></article>@endforeach</div>
            @endif
        </section>
    </main>
</x-layouts.app>
