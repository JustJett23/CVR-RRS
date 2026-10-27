<x-layouts.app title="CVR" page-title="Dashboard" current-page="dashboard">
    <main id="main-content" class="dashboard">
        <div class="page-heading">
            <div>
                <p class="eyebrow">FACILITY COMMAND CENTER <span class="eyebrow-line"></span></p>
                <h1>Campus overview</h1>
                <p class="page-subtitle">A clear view of the spaces, reservations, and activity across campus.</p>
            </div>
            <div class="heading-meta">
                <span class="date-label">{{ now()->format('D, M j, Y') }}</span>
                @unless (auth()->user()?->role === 'student')
                    <a class="primary-button" href="{{ route('reservations') }}"><span aria-hidden="true">＋</span> Reservation guide</a>
                @endunless
            </div>
        </div>

        <x-floor-plan-workspace :rooms-by-floor="$roomsByFloor">
            <section class="metrics-grid" aria-label="Campus room summary">
                <x-metric-card label="Total rooms" :value="$metrics['totalRooms']" caption="Listed in the room directory" context="LIVE" icon="door-closed" tone="primary" />
                <x-metric-card label="Available now" :value="$metrics['availableRooms']" :caption="auth()->user()?->role === 'student' ? 'Ready for use' : 'Ready to reserve'" context="LIVE" icon="door-open" tone="green" />
                @if (in_array(auth()->user()?->role, ['admin', 'creator'], true))
                    <x-metric-card label="Today's reservations" :value="$metrics['todayReservations']" caption="Current bookings" context="LIVE" icon="calendar-check" />
                    <x-metric-card label="Pending requests" :value="$metrics['pendingRequests']" caption="Awaiting review" context="LIVE" icon="hourglass" tone="amber" />
                    <x-metric-card label="Active issues" :value="$metrics['activeIssues']" caption="Maintenance follow-up" context="LIVE" icon="wrench" tone="issue" />
                @endif
            </section>
        </x-floor-plan-workspace>

        <footer class="dashboard-footer"><span>Campus Venue Engine <span class="footer-divider">·</span> Navotas Polytechnic College</span><span>Live campus database</span></footer>
    </main>
</x-layouts.app>
