<x-layouts.app title="CVR" page-title="Notifications" current-page="notifications">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="UPDATES" title="Notification center" description="Read full reservation, equipment, and account updates from your campus account." />

        @if (session('status'))
            <div class="notice-panel notice-panel-success reservation-feedback" role="status"><x-icon name="check" />{{ session('status') }}</div>
        @endif

        <section class="content-panel notification-center-panel" aria-labelledby="notification-center-title">
            <div class="content-panel-heading"><div><p class="eyebrow">{{ number_format($unreadCount) }} UNREAD</p><h2 id="notification-center-title">Your notifications</h2></div>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="secondary-button" type="submit">Mark all as read</button></form>
                @endif
            </div>

            @php($notificationFilters = $isStudent ? ['all' => 'All', 'system' => 'System'] : ['all' => 'All', 'reservations' => 'Reservations', 'equipment' => 'Equipment', 'system' => 'System'])
            <nav class="notification-filters" aria-label="Filter notifications">
                @foreach ($notificationFilters as $key => $label)
                    <a href="{{ route('notifications.index', ['filter' => $key]) }}" @class(['is-current' => $filter === $key]) @if ($filter === $key) aria-current="page" @endif>{{ $label }} <span>{{ number_format($counts[$key]) }}</span></a>
                @endforeach
            </nav>

            @forelse ($notifications as $notification)
                <article class="notification-card {{ $notification->read_at ? '' : 'is-unread' }}" aria-labelledby="notification-{{ $notification->id }}-title">
                    <div class="notification-card-icon" aria-hidden="true"><x-icon :name="$notification->type === 'equipment_status' ? 'package' : ($notification->type === 'reservation_status' ? 'calendar-check' : 'bell')" /></div>
                    <div class="notification-card-content">
                        <div class="notification-card-heading"><h3 id="notification-{{ $notification->id }}-title">{{ $notification->title }}</h3><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M j, Y · g:i A') }}</time></div>
                        <p class="notification-card-message">{{ $notification->message }}</p>
                        @if (is_array($notification->data) && count($notification->data) > 0)
                            <dl class="notification-data">
                                @foreach ($notification->data as $key => $value)
                                    @continue(! is_scalar($value) || $key === 'qr_token')
                                    <div><dt>{{ str($key)->replace('_', ' ')->headline() }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}" class="notification-read-form">@csrf<button class="secondary-button" type="submit">Mark read</button></form>
                    @else
                        <span class="notification-read-label">Read</span>
                    @endif
                </article>
            @empty
                <x-empty-state icon="bell" eyebrow="ALL CAUGHT UP" title="No notifications here" description="New campus updates will appear here with their full message and details." />
            @endforelse

            @if ($notifications->hasPages())
                <div class="activity-pagination">{{ $notifications->links() }}</div>
            @endif
        </section>
    </main>
</x-layouts.app>
