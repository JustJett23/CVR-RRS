<x-layouts.app title="CVR" page-title="Equipment Requests" current-page="equipment-requests">
    <main class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Equipment Requests" description="Request equipment for an approved room reservation and follow its return status." />

        <section class="content-panel" aria-labelledby="request-status-title">
            <div class="content-panel-heading"><div><p class="eyebrow">REQUEST LIFECYCLE</p><h2 id="request-status-title">From request to return</h2></div><span class="sample-tag">Request service not connected</span></div>
            <div class="status-lifecycle" aria-label="Equipment request statuses">
                <span class="availability-chip is-pending">Pending</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip is-approved">Approved</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Released</span><span class="lifecycle-arrow" aria-hidden="true">→</span>
                <span class="availability-chip">Returned</span>
                <span class="availability-chip is-rejected">Rejected</span>
            </div>
            <x-empty-state icon="list-checks" eyebrow="NO REQUEST RECORDS" title="Equipment requests will appear here" description="This area needs equipment inventory, reservation records, and permission-aware request handling before it can accept or track a request." action-label="Browse equipment" :action-href="route('equipment')" />
        </section>
    </main>
</x-layouts.app>
