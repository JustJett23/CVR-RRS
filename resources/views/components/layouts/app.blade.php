@props(['title' => 'CVR', 'pageTitle' => 'Dashboard', 'currentPage' => 'dashboard'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153548">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/campus-venue-engine-logo.svg') }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell" id="app-shell">
        <x-campus-sidebar :active="$currentPage" />

        <div class="main-column">
            <header class="topbar">
                <button class="icon-button menu-toggle" id="sidebar-toggle" type="button" aria-label="Collapse navigation" aria-controls="sidebar" aria-expanded="true" title="Collapse navigation">
                    <x-icon name="menu" />
                </button>
                <a class="mobile-brand" href="{{ route('dashboard') }}" aria-label="Campus Venue Engine dashboard"><img src="{{ asset('images/campus-venue-engine-logo.svg') }}" alt=""><span>Campus Venue Engine</span></a>
                <div class="breadcrumb"><a href="{{ route('dashboard') }}">Campus</a><span class="breadcrumb-divider">/</span><strong>{{ $pageTitle }}</strong></div>
                <div class="topbar-actions">
                    <span class="demo-pill"><span class="demo-dot"></span> Preview mode</span>
                    <button class="icon-button notification-button" type="button" aria-label="Notifications are not connected yet" title="Notifications are not connected yet" data-tooltip="Notifications are not connected yet" disabled><x-icon name="bell" /></button>
                </div>
            </header>

            {{ $slot }}
        </div>

        <div class="mobile-scrim" id="mobile-scrim" hidden></div>
        <div class="toast" id="app-toast" role="status" aria-live="polite" hidden></div>
    </div>
</body>
</html>
