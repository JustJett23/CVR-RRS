<x-layouts.app title="CVR" page-title="Help & Support" current-page="help">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="SYSTEM" title="Help & Support" description="Find guidance for using the campus room reservation system." />

        <nav class="help-shortcuts" aria-label="Help topics">
            <a class="help-shortcut" href="{{ route('help.faqs') }}"><span class="help-shortcut-icon"><x-icon name="list-checks" /></span><span>FAQs</span></a>
            <a class="help-shortcut" href="{{ route('help.chatbot') }}"><span class="help-shortcut-icon"><x-icon name="help" /></span><span>Chatbot</span></a>
            <a class="help-shortcut" href="#contact-details"><span class="help-shortcut-icon"><x-icon name="building" /></span><span>Contact Details</span></a>
        </nav>

        <section class="help-cards" aria-label="Help and support options">
            <article class="content-panel help-card">
                <span class="help-card-mark"><x-icon name="list-checks" /></span>
                <p class="eyebrow">QUICK ANSWERS</p>
                <h2>Frequently asked questions</h2>
                <p>Browse answers about room availability, reservation requests, approvals, and account access.</p>
                <a class="secondary-button" href="{{ route('help.faqs') }}">Browse FAQs <span aria-hidden="true">↗</span></a>
            </article>

            <article class="content-panel help-card">
                <span class="help-card-mark"><x-icon name="help" /></span>
                <p class="eyebrow">GUIDED HELP</p>
                <h2>Room reservation chatbot</h2>
                <p>Ask the AI help assistant how to use rooms, schedules, and reservation requests. It uses sample guidance only and cannot view live records.</p>
                <a class="secondary-button" href="{{ route('help.chatbot') }}">Open chatbot <span aria-hidden="true">↗</span></a>
            </article>

            <article class="content-panel help-card contact-card" id="contact-details">
                <span class="help-card-mark"><x-icon name="building" /></span>
                <p class="eyebrow">CAMPUS SUPPORT</p>
                <h2>Contact details</h2>
                <p>Contact the campus support team for help with the reservation system.</p>
                <dl class="contact-details-list">
                    <div><dt>Email</dt><dd>Official support email to be provided</dd></div>
                    <div><dt>Phone</dt><dd>Official support phone to be provided</dd></div>
                    <div><dt>Office hours</dt><dd>To be confirmed by campus support</dd></div>
                </dl>
            </article>
        </section>
    </main>
</x-layouts.app>
