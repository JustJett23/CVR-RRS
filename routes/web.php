<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentRequestController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReservationWorkflowController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RrsChatbotController;
use App\Http\Controllers\SettingsController;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\AccountRolePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function (Request $request): RedirectResponse|View {
    $user = $request->user();

    if ($user instanceof User && $user->is_active) {
        return to_route('dashboard', ['role' => AccountRolePath::for($user)]);
    }

    return view('landing');
})->name('home');
Route::get('/landing', function (): Response {
    return response()
        ->view('landing')
        ->header('Cache-Control', 'public, max-age=300, must-revalidate')
        ->header('Vercel-CDN-Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=86400');
})->name('landing');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/login/lookup-id', [AuthController::class, 'lookupExistingStudent'])->middleware('throttle:id-verification')->name('login.lookup-id');
Route::post('/login/verify-id', [AuthController::class, 'verifyId'])->middleware('throttle:id-verification')->name('login.verify-id');

Route::middleware(['auth', 'account-role-prefix'])->prefix('{role}')->where(['role' => '[a-z][a-z0-9-]*'])->group(function (): void {
    Route::get('/', fn () => to_route('dashboard'))->name('account.home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('notifications.read');
    Route::get('/dashboard/rooms', [DashboardController::class, 'rooms'])->name('dashboard.rooms');
    Route::get('/dashboard/floor-plan-data', [DashboardController::class, 'floorPlanData'])->name('dashboard.floor-plan-data');
    Route::get('/floor-plan', [DashboardController::class, 'floorPlan'])->name('floor-plan');
    Route::get('/floor-plan/source', function (): BinaryFileResponse {
        abort_unless(Auth::user()?->is_active === true, 403);

        return response()->file(storage_path('app/private/npc-floorplans.pdf'), [
            'Cache-Control' => 'private, no-store',
        ]);
    })->name('floor-plan.source');
    Route::post('/reservations/{reservation}/approve', [ReservationWorkflowController::class, 'approve'])->name('reservations.approve');
    Route::post('/reservations/{reservation}/reject', [ReservationWorkflowController::class, 'reject'])->name('reservations.reject');
    Route::post('/reservations/{reservation}/cancel', [ReservationWorkflowController::class, 'cancel'])->name('reservations.cancel');

    Route::middleware('throttle:api')->prefix('api')->name('api.')->group(function (): void {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    });

    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::get('/reservations', function (Request $request): RedirectResponse|View {
        if (Auth::user()?->role === 'admin') {
            return to_route('rooms');
        }

        abort_unless(Auth::user()?->role !== 'student', 403);
        $rooms = Room::query()
            ->select(['id', 'floor_id', 'code', 'name'])
            ->where('status', 'active')
            ->with('floor:id,number')
            ->orderBy('code')
            ->get();

        $user = Auth::user();
        $isStaff = $user?->is_active && in_array($user->role, ['admin', 'creator'], true);
        $reservations = Reservation::query()
            ->with(['room.floor.building', 'user'])
            ->when(! $isStaff, fn ($query) => $query->where('user_id', $user?->getKey()))
            ->latest('starts_at')
            ->limit(100)
            ->get();

        $selectedRoom = null;

        if ($request->filled('room_id')) {
            $selectedRoom = Room::query()
                ->where('status', 'active')
                ->with('floor.building')
                ->findOrFail($request->integer('room_id'));
        }

        return view('pages.reservations', [
            'rooms' => $rooms,
            'reservations' => $reservations,
            'isStaff' => $isStaff,
            'selectedRoom' => $selectedRoom,
            'defaultStart' => $selectedRoom ? now()->addHour()->format('Y-m-d\\TH:i') : null,
        ]);
    })->name('reservations');
    Route::get('/schedule', function (): View {
        return view('pages.schedule', [
            'roomTypes' => Room::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
        ]);
    })->name('schedule');
    Route::view('/availability', 'pages.availability')->name('availability');
    Route::get('/equipment', function (): View {
        return view('pages.equipment', [
            'equipment' => Equipment::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get(),
        ]);
    })->name('equipment');
    Route::get('/equipment-requests', [EquipmentRequestController::class, 'index'])->name('equipment-requests');
    Route::post('/equipment-requests', [EquipmentRequestController::class, 'store'])->name('equipment-requests.store');

    Route::middleware('deny-student-operations')->group(function (): void {
        Route::post('/equipment-requests/{equipmentRequest}/approve', [EquipmentRequestController::class, 'approve'])->name('equipment-requests.approve');
        Route::post('/equipment-requests/{equipmentRequest}/reject', [EquipmentRequestController::class, 'reject'])->name('equipment-requests.reject');
        Route::post('/equipment-requests/{equipmentRequest}/release', [EquipmentRequestController::class, 'release'])->name('equipment-requests.release');
        Route::post('/equipment-requests/{equipmentRequest}/return', [EquipmentRequestController::class, 'return'])->name('equipment-requests.return');
    });
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/profile', [SettingsController::class, 'profile'])->name('profile');
    Route::get('/profile/photo', [SettingsController::class, 'photo'])->name('profile.photo');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::put('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::view('/help', 'pages.help')->name('help');
    Route::view('/help/faqs', 'pages.faqs')->name('help.faqs');
    Route::view('/help/chatbot', 'pages.chatbot')->name('help.chatbot');
    Route::post('/help/chatbot', RrsChatbotController::class)->middleware('throttle:rrs-chat')->name('help.chatbot.ask');
    Route::get('/help/reservation-steps', function (): View {
        abort_unless(Auth::user()?->role !== 'student', 403);

        return view('pages.reservation-steps');
    })->name('help.reservation-steps');
});

Route::middleware(['auth', 'account-role-prefix'])->group(function (): void {
    Route::get('/student/dashboard', [DashboardController::class, 'index'])->defaults('role', 'student')->name('dashboard.student');
    Route::get('/org-leader/dashboard', [DashboardController::class, 'index'])->defaults('role', 'org-leader')->name('dashboard.org-leader');
    Route::get('/professor/dashboard', [DashboardController::class, 'index'])->defaults('role', 'professor')->name('dashboard.professor');
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->defaults('role', 'admin')->name('dashboard.admin');
    Route::get('/creator/dashboard', [DashboardController::class, 'index'])->defaults('role', 'creator')->name('dashboard.creator');
});

Route::middleware('auth')->get('/{legacyPath}', function (Request $request, string $legacyPath): RedirectResponse {
    $user = $request->user();
    abort_unless($user instanceof User && $user->is_active, 403);

    $segments = explode('/', trim($legacyPath, '/'));
    abort_if(AccountRolePath::recognizes($segments[0] ?? ''), 404);

    abort_unless(AccountRolePath::isLegacyPath($segments[0] ?? ''), 404);

    if (($segments[0] ?? '') === 'dashboard' && AccountRolePath::recognizes($segments[1] ?? '')) {
        array_splice($segments, 1, 1);
    }

    $destination = '/'.AccountRolePath::for($user).'/'.implode('/', $segments);
    $query = $request->getQueryString();

    return redirect()->to($destination.($query === null ? '' : '?'.$query));
})->where('legacyPath', '.*')->name('legacy.role-prefix');
