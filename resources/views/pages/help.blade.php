<x-layouts.app title="CVR" page-title="Help & Support" current-page="help">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="SYSTEM" title="Help & Support" description="Quick guidance for navigating the campus space database." />

        <div class="help-grid">
            <section class="content-panel faq-panel" aria-labelledby="faq-title">
                <div class="content-panel-heading"><div><p class="eyebrow">QUICK ANSWERS</p><h2 id="faq-title">Frequently asked questions</h2></div></div>
                <details class="faq-item"><summary>Are the room statuses live?</summary><p>Yes. Room status is read from the database and checked against reservations and maintenance windows.</p></details>
                <details class="faq-item"><summary>Can I submit a room reservation here?</summary><p>Yes. Sign in, choose a room and time, then submit the request for server-side conflict checking.</p></details>
                <details class="faq-item"><summary>How do I get help planning a reservation?</summary><p>Use the reservation steps or the campus help assistant for guided answers.</p></details>
            </section>

            <aside class="support-card">
                <span class="support-mark"><x-icon name="help" /></span>
                <p class="eyebrow">SUPPORT CHANNEL</p>
                <h2>Campus support</h2>
                <p>Choose a guided help page for reservation planning or common campus questions.</p>
                <a class="support-disabled support-link" href="{{ route('help.chatbot') }}">Open help assistant</a>
                <a class="support-disabled support-link" href="{{ route('help.reservation-steps') }}">Read reservation steps</a>
            </aside>
        </div>
        <div class="page-actions"><a class="secondary-button" href="{{ route('help.chatbot') }}">Help assistant</a><a class="text-link" href="{{ route('help.reservation-steps') }}">Reservation steps</a></div>
    </main>
</x-layouts.app>
