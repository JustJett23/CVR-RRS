@props(['active' => 'dashboard'])

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="{{ route('dashboard') }}" aria-label="Campus Venue Engine dashboard">
        <img class="brand-mark" src="{{ asset('images/campus-venue-engine-logo.svg') }}" alt="">
        <span class="brand-copy">
            <span class="brand-name">Campus Venue Engine</span>
            <span class="brand-school">Navotas Polytechnic College</span>
        </span>
    </a>

    <nav class="sidebar-nav">
        <div class="nav-group">
            <p class="nav-heading">Workspace</p>
            <a class="nav-link {{ $active === 'dashboard' ? 'is-current' : '' }}" href="{{ route('dashboard') }}" title="Dashboard" @if ($active === 'dashboard') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="dashboard" /></span><span class="nav-label">Dashboard</span></a>
            <a class="nav-link {{ $active === 'floor-plan' ? 'is-current' : '' }}" href="{{ route('floor-plan') }}" title="3D Floor Plan" @if ($active === 'floor-plan') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="layers" /></span><span class="nav-label">3D Floor Plan</span></a>
            <a class="nav-link {{ $active === 'rooms' ? 'is-current' : '' }}" href="{{ route('rooms') }}" title="Room Directory" @if ($active === 'rooms') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="door-open" /></span><span class="nav-label">Room Directory</span></a>
        </div>

        <div class="nav-group">
            <p class="nav-heading">Planning</p>
            <a class="nav-link {{ $active === 'reservations' ? 'is-current' : '' }}" href="{{ route('reservations') }}" title="Reservations" @if ($active === 'reservations') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="calendar-check" /></span><span class="nav-label">Reservations</span></a>
            <a class="nav-link {{ $active === 'schedule' ? 'is-current' : '' }}" href="{{ route('schedule') }}" title="Schedule Timeline" @if ($active === 'schedule') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="calendar" /></span><span class="nav-label">Schedule Timeline</span></a>
            <a class="nav-link {{ $active === 'availability' ? 'is-current' : '' }}" href="{{ route('availability') }}" title="Room Availability" @if ($active === 'availability') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="clock" /></span><span class="nav-label">Room Availability</span></a>
        </div>

        <div class="nav-group">
            <p class="nav-heading">Operations</p>
            <a class="nav-link {{ $active === 'equipment' ? 'is-current' : '' }}" href="{{ route('equipment') }}" title="Equipment" @if ($active === 'equipment') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="package" /></span><span class="nav-label">Equipment</span></a>
            <a class="nav-link {{ $active === 'equipment-requests' ? 'is-current' : '' }}" href="{{ route('equipment-requests') }}" title="Equipment Requests" @if ($active === 'equipment-requests') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="list-checks" /></span><span class="nav-label">Equipment Requests</span></a>
        </div>

        <div class="nav-group">
            <p class="nav-heading">Insights</p>
            <a class="nav-link {{ $active === 'reports' ? 'is-current' : '' }}" href="{{ route('reports') }}" title="Insights and Reports" @if ($active === 'reports') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="chart" /></span><span class="nav-label">Insights &amp; Reports</span></a>
            <a class="nav-link {{ $active === 'activity-logs' ? 'is-current' : '' }}" href="{{ route('activity-logs') }}" title="Activity Logs" @if ($active === 'activity-logs') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="activity" /></span><span class="nav-label">Activity Logs</span></a>
        </div>

        <div class="nav-group">
            <p class="nav-heading">System</p>
            <a class="nav-link {{ $active === 'settings' ? 'is-current' : '' }}" href="{{ route('settings') }}" title="Settings" @if ($active === 'settings') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="settings" /></span><span class="nav-label">Settings</span></a>
            <a class="nav-link {{ $active === 'help' ? 'is-current' : '' }}" href="{{ route('help') }}" title="Help and Support" @if ($active === 'help') aria-current="page" @endif><span class="nav-glyph" aria-hidden="true"><x-icon name="help" /></span><span class="nav-label">Help &amp; Support</span></a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="profile-avatar" aria-hidden="true">JC</div>
        <div class="profile-copy"><span class="profile-name">Jet Carlos</span><span class="profile-role">Preview profile</span></div>
        <span class="profile-menu" aria-hidden="true">···</span>
    </div>
</aside>
