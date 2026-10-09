<x-layouts.app title="CVR" page-title="Dashboard" current-page="dashboard">
    <main class="dashboard" id="dashboard">
        <div class="page-heading">
            <div>
                <p class="eyebrow">FACILITY COMMAND CENTER <span class="eyebrow-line"></span></p>
                <h1>Campus overview</h1>
                <p class="page-subtitle">A clear view of the spaces, reservations, and activity across campus.</p>
            </div>
            <div class="heading-meta">
                <span class="date-label">{{ now()->format('D, M j, Y') }}</span>
                <a class="primary-button" href="{{ route('reservations') }}"><span aria-hidden="true">＋</span> Reservation guide</a>
            </div>
        </div>

        <x-floor-plan-workspace :rooms-by-floor="$roomsByFloor">
            <section class="metrics-grid" aria-label="Illustrative campus summary">
                <x-metric-card label="Total rooms" value="24" caption="Across 3 buildings" context="SAMPLE" icon="door-closed" tone="primary" />
                <x-metric-card label="Available now" value="08" caption="Ready to reserve" context="NOW" icon="door-open" tone="green" />
                <x-metric-card label="Today's reservations" value="12" caption="Across 5 organizations" context="TODAY" icon="calendar-check" />
                <x-metric-card label="Pending requests" value="03" caption="Awaiting review" context="ACTION" icon="hourglass" tone="amber" />
                <x-metric-card label="Active issues" value="02" caption="Maintenance follow-up" context="SAMPLE" icon="wrench" tone="issue" />
            </section>
        </x-floor-plan-workspace>

        <div class="sample-notice" role="note">
            <span class="notice-mark" aria-hidden="true">i</span>
            <span><strong>Illustrative dashboard.</strong> Room counts, schedules, and floor geometry are sample content pending verified campus inventory and building plans.</span>
        </div>

        <footer class="dashboard-footer"><span>Campus Venue Engine <span class="footer-divider">·</span> Navotas Polytechnic College</span><span>Interface preview · sample data</span></footer>
    </main>
</x-layouts.app>
