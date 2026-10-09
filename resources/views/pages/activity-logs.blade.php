<x-layouts.app title="CVR" page-title="Activity Logs" current-page="activity-logs">
    <main class="dashboard page-content">
        <x-page-title eyebrow="INSIGHTS" title="Activity Logs" description="Review reservation, facility, and equipment events with a clear record of who did what and when." />

        <section class="content-panel" aria-labelledby="activity-feed-title">
            <div class="content-panel-heading"><div><p class="eyebrow">SYSTEM HISTORY</p><h2 id="activity-feed-title">Recent activity</h2></div><span class="sample-tag">Audit log not connected</span></div>
            <x-empty-state icon="activity" eyebrow="NO AUDIT EVENTS" title="Activity history is not available" description="Activity entries should be written by the server and protected by role-based access. No example events are shown as real campus activity." action-label="Review system status" :action-href="route('settings')" />
        </section>
    </main>
</x-layouts.app>
