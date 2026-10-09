<x-layouts.app title="CVR" page-title="Settings" current-page="settings">
    <main class="dashboard page-content">
        <x-page-title eyebrow="SYSTEM" title="Settings" description="Account and campus preferences will be managed here when sign-in and organization access are configured." />

        <section class="settings-grid" aria-label="Settings categories">
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="dashboard" /></span><div><h2>Profile</h2><p>Display name and profile details</p></div><span class="settings-state">Account required</span></article>
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="settings" /></span><div><h2>Account</h2><p>Sign-in, security, and account access</p></div><span class="settings-state">Authentication required</span></article>
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="bell" /></span><div><h2>Notifications</h2><p>Reservation and equipment updates</p></div><span class="settings-state">Not connected</span></article>
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="calendar-check" /></span><div><h2>Reservation preferences</h2><p>Default durations and request details</p></div><span class="settings-state">Booking service required</span></article>
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="building" /></span><div><h2>Organization</h2><p>Membership and organization access</p></div><span class="settings-state">Organization records required</span></article>
            <article class="settings-card"><span class="settings-card-icon"><x-icon name="layers" /></span><div><h2>System preferences</h2><p>Interface and campus defaults</p></div><span class="settings-state">Not configured</span></article>
        </section>

        <div class="notice-panel" role="note"><span class="notice-mark" aria-hidden="true">i</span><p>Settings are read-only in this frontend preview. Changes will require signed-in accounts, permission checks, and saved server-side preferences.</p></div>
    </main>
</x-layouts.app>
