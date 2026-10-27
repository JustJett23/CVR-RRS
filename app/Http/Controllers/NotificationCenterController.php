<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    /**
     * @var array<string, array<int, string>>
     */
    private const FILTER_TYPES = [
        'reservations' => ['reservation_status'],
        'equipment' => ['equipment_status'],
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $isStudent = $user->role === 'student';
        $validated = $request->validate([
            'filter' => ['sometimes', 'string', 'in:all,reservations,equipment,system'],
        ]);
        $filter = $validated['filter'] ?? 'all';
        if ($isStudent && in_array($filter, ['reservations', 'equipment'], true)) {
            $filter = 'all';
        }
        $systemTypes = array_merge(...array_values(self::FILTER_TYPES));
        $notifications = $user->userNotifications()
            ->when(
                isset(self::FILTER_TYPES[$filter]),
                fn ($query) => $query->whereIn('type', self::FILTER_TYPES[$filter]),
            )
            ->when($filter === 'system', fn ($query) => $query->whereNotIn('type', $systemTypes))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all' => $user->userNotifications()->count(),
            'reservations' => $user->userNotifications()->whereIn('type', self::FILTER_TYPES['reservations'])->count(),
            'equipment' => $user->userNotifications()->whereIn('type', self::FILTER_TYPES['equipment'])->count(),
            'system' => $user->userNotifications()->whereNotIn('type', $systemTypes)->count(),
        ];

        return view('pages.notifications', [
            'notifications' => $notifications,
            'filter' => $filter,
            'counts' => $counts,
            'unreadCount' => $user->userNotifications()->whereNull('read_at')->count(),
            'isStudent' => $isStudent,
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 404);
        $notification->update(['read_at' => now()]);

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
