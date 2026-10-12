@props(['title' => 'CVR', 'pageTitle' => 'Dashboard', 'currentPage' => 'dashboard'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#27323B">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/campus-venue-engine-logo.svg') }}">
    <title>{{ $pageTitle }} · {{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-user-role="{{ auth()->user()?->role ?? 'guest' }}">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell" id="app-shell">
        <x-campus-sidebar :active="$currentPage" />

        <div class="main-column">
            <header class="topbar" aria-label="Top navigation">
                <button class="icon-button menu-toggle" id="sidebar-toggle" type="button" aria-label="Collapse navigation" aria-controls="sidebar" aria-expanded="true" title="Collapse navigation">
                    <x-icon name="menu" />
                </button>
                <a class="mobile-brand" href="{{ route('dashboard') }}" aria-label="Campus Venue Engine dashboard"><img src="{{ asset('images/campus-venue-engine-logo.svg') }}" alt="" width="30" height="30"><span>Campus Venue Engine</span></a>
                <div class="breadcrumb"><a href="{{ route('dashboard') }}">Campus</a><span class="breadcrumb-divider">/</span><strong>{{ $pageTitle }}</strong></div>
                <div class="topbar-actions">
                    <span class="demo-pill"><span class="demo-dot"></span> Database mode</span>
                    <div class="popover-anchor">
                        @php($notifications = auth()->user()->userNotifications()->latest()->limit(5)->get())
                        @php($unreadNotifications = $notifications->whereNull('read_at')->count())
                        <button class="icon-button notification-button" id="notification-toggle" type="button" aria-label="Open notifications" title="Open notifications" data-tooltip="Open notifications" aria-controls="notification-popover" aria-expanded="false"><x-icon name="bell" /><span class="notification-dot" aria-hidden="true" @if($unreadNotifications === 0) hidden @endif></span></button>
                        <div class="popover notification-popover" id="notification-popover" role="status" aria-live="polite" hidden>
                            <div class="popover-heading"><strong>Notifications</strong><span class="popover-kicker">DATABASE</span></div>
                            @forelse($notifications as $notification)
                                <div class="notification-item {{ $notification->read_at ? '' : 'is-unread' }}" data-notification-id="{{ $notification->id }}"><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p><small class="notification-time" data-created-at="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</small></div>
                            @empty
                                <p>No new notifications. Connected alerts will appear here.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </header>

            {{ $slot }}
        </div>

        <div class="mobile-scrim" id="mobile-scrim" hidden></div>
        <div class="toast notification-toast" id="app-toast" role="status" aria-live="polite" data-server-toast="{{ session('onboarding_notification') }}" hidden>
            <div class="toast-header">
                <span class="toast-status" aria-hidden="true"><x-icon name="check" /></span>
                <strong data-toast-title>Your NPC access is ready</strong>
                <button class="toast-control toast-expand" type="button" data-toast-toggle aria-expanded="false" aria-label="Expand notification">⌃</button>
                <button class="toast-control toast-close" type="button" data-toast-close aria-label="Close notification">×</button>
            </div>
            <div class="toast-detail" data-toast-detail hidden>
                <span data-toast-message></span>
                <button class="toast-pause" type="button" data-toast-pause>Click to keep open.</button>
            </div>
            <div class="toast-progress" aria-hidden="true"><span data-toast-progress></span></div>
        </div>
    </div>
    <x-loading-indicator />
</body>
</html>
