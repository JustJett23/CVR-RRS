<x-layouts.app title="CVR" page-title="Help & Support" current-page="help">
    <main class="dashboard page-content">
        <x-page-title eyebrow="SYSTEM" title="Help & Support" description="Quick guidance for navigating the campus space preview and understanding the reservation workflow." />

        <div class="help-grid">
            <section class="content-panel faq-panel" aria-labelledby="faq-title">
                <div class="content-panel-heading"><div><p class="eyebrow">QUICK ANSWERS</p><h2 id="faq-title">Frequently asked questions</h2></div></div>
                <details class="faq-item"><summary>Are the room statuses live?</summary><p>No. Room names, counts, statuses, and floor geometry are illustrative data. Confirmed availability needs an approved inventory and reservation schedule.</p></details>
                <details class="faq-item"><summary>Can I submit a room reservation here?</summary><p>Not in this preview. The booking service must check current reservations and maintenance windows on the server before accepting any request.</p></details>
                <details class="faq-item"><summary>How do I explore the floor layout?</summary><p>Open the 3D Floor Plan. Drag to rotate, scroll or use the floating controls to zoom, and select a sample room to inspect its illustrative details.</p></details>
                <details class="faq-item"><summary>Where can I get campus support?</summary><p>An official administrator contact has not been provided yet. Contact details will be added after the college confirms the correct support channel.</p></details>
            </section>

            <aside class="support-card">
                <span class="support-mark"><x-icon name="help" /></span>
                <p class="eyebrow">SUPPORT CHANNEL</p>
                <h2>Campus contact details pending</h2>
                <p>We need an approved Navotas Polytechnic College administrator contact before this page can provide a support link.</p>
                <span class="support-disabled">Contact administrator · not configured</span>
                <span class="support-disabled">Report a problem · not connected</span>
            </aside>
        </div>
        <div class="page-actions"><a class="secondary-button" href="{{ route('reservations') }}">Read reservation steps</a><a class="text-link" href="{{ route('floor-plan') }}">Open the 3D floor plan</a></div>
    </main>
</x-layouts.app>
