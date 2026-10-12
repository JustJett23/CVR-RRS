<x-layouts.app title="CVR" page-title="Help Assistant" current-page="help">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="SUPPORT" title="Help Assistant" description="Search common campus room and reservation questions." />
        <section class="content-panel chatbot-panel" aria-labelledby="chatbot-title">
            <div class="content-panel-heading"><div><p class="eyebrow">GUIDED HELP</p><h2 id="chatbot-title">Ask about rooms or reservations</h2></div><span class="live-indicator"><i></i> Connected to campus guidance</span></div>
            <div class="chatbot-messages" aria-live="polite"><p class="chatbot-message">Ask about room availability, scheduling, or submitting a reservation.</p></div>
            <form class="chatbot-form" data-chatbot-form><label class="sr-only" for="chatbot-question">Your question</label><input id="chatbot-question" name="question" placeholder="For example: how do I reserve a room?" autocomplete="off"><button class="primary-button" type="submit">Ask</button></form>
        </section>
    </main>
</x-layouts.app>
