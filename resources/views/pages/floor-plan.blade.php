<x-layouts.app title="CVR" page-title="3D Floor Plan" current-page="floor-plan">
    <main class="dashboard page-content">
        <x-page-title eyebrow="SPATIAL VIEW" title="3D Floor Plan" description="Explore the illustrative floor layout and inspect sample room details." />
        <x-floor-plan-workspace :rooms-by-floor="$roomsByFloor" :initial-floor="$initialFloor" />
        <div class="sample-notice" role="note"><span class="notice-mark" aria-hidden="true">i</span><span><strong>Preview geometry.</strong> This interactive model demonstrates map controls; official building plans and room records must be connected before use.</span></div>
    </main>
</x-layouts.app>
