<x-layouts.app title="CVR" page-title="3D Floor Plan" current-page="floor-plan">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="SPATIAL VIEW" title="3D Floor Plan" description="Explore the NPC building model and inspect live room availability by floor." />
        <div class="page-actions"><a class="text-link" href="{{ route('floor-plan.source') }}" target="_blank" rel="noopener">Open NPC source floor plans</a></div>
        <x-floor-plan-workspace :rooms-by-floor="$roomsByFloor" :initial-floor="$initialFloor" />
        <div class="sample-notice" role="note"><span class="notice-mark" aria-hidden="true">i</span><span><strong>Live data connection.</strong> Floor selection refreshes room status and reservations from the CVR database.</span></div>
    </main>
</x-layouts.app>
