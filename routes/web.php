<?php

use App\Http\Controllers\Api\EquipmentInventoryController;
use App\Http\Controllers\Api\EquipmentRequestController;
use App\Http\Controllers\Api\HelpAssistantController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\RoomDirectoryController;
use App\Http\Controllers\SettingsController;
use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', function (): RedirectResponse|Response {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return response()->view('landing');
})->name('home');
Route::get('/landing', function (): Response {
    return response()
        ->view('landing')
        ->header('Cache-Control', 'public, max-age=300, must-revalidate')
        ->header('Vercel-CDN-Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=86400');
})->name('landing');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/login/verify-id', [AuthController::class, 'verifyId'])->middleware('throttle:id-verification')->name('login.verify-id');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/notifications/{notification}/read', function (UserNotification $notification): RedirectResponse {
        abort_unless($notification->user_id === Auth::id(), 403);

        $notification->update(['read_at' => now()]);

        return back();
    })->name('notifications.read');
    Route::get('/dashboard/rooms', [DashboardController::class, 'rooms'])->name('dashboard.rooms');
    Route::get('/dashboard/floor-plan-data', [DashboardController::class, 'floorPlanData'])->name('dashboard.floor-plan-data');
    Route::get('/floor-plan', [DashboardController::class, 'floorPlan'])->name('floor-plan');

    Route::middleware('throttle:api')->prefix('api')->name('api.')->group(function (): void {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('/reservations/{reservation}/review', [ReservationController::class, 'decide'])->name('reservations.review');
        Route::get('/rooms', [RoomDirectoryController::class, 'data'])->name('rooms.index');
        Route::post('/help/ask', [HelpAssistantController::class, 'ask'])->name('help.ask');
        Route::get('/equipment', [EquipmentInventoryController::class, 'index'])->name('equipment.index');
        Route::get('/equipment/{equipment}', [EquipmentInventoryController::class, 'show'])->name('equipment.show');
        Route::post('/equipment', [EquipmentInventoryController::class, 'store'])->name('equipment.store');
        Route::put('/equipment/{equipment}', [EquipmentInventoryController::class, 'update'])->name('equipment.update');
        Route::get('/equipment-requests', [EquipmentRequestController::class, 'index'])->name('equipment-requests.index');
        Route::post('/equipment-requests', [EquipmentRequestController::class, 'store'])->name('equipment-requests.store');
        Route::get('/equipment-requests/{equipmentRequest}', [EquipmentRequestController::class, 'show'])->name('equipment-requests.show');
        Route::put('/equipment-requests/{equipmentRequest}', [EquipmentRequestController::class, 'update'])->name('equipment-requests.update');
        Route::post('/equipment-requests/{equipmentRequest}/cancel', [EquipmentRequestController::class, 'cancel'])->name('equipment-requests.cancel');
        Route::post('/equipment-requests/{equipmentRequest}/review', [EquipmentRequestController::class, 'review'])->name('equipment-requests.review');
        Route::post('/equipment-requests/{equipmentRequest}/release', [EquipmentRequestController::class, 'release'])->name('equipment-requests.release');
        Route::post('/equipment-requests/{equipmentRequest}/return', [EquipmentRequestController::class, 'returnEquipment'])->name('equipment-requests.return');
    });

    Route::get('/rooms', [RoomDirectoryController::class, 'index'])->name('rooms');
    Route::get('/reservations', function (): View {
        $rooms = Room::query()
            ->select(['id', 'floor_id', 'code', 'name'])
            ->where('status', 'active')
            ->with('floor:id,number')
            ->orderBy('code')
            ->get();

        return view('pages.reservations', [
            'rooms' => $rooms,
            'isReservationReviewer' => in_array(Auth::user()->role, ['admin', 'creator'], true),
        ]);
    })->name('reservations');
    Route::view('/schedule', 'pages.schedule')->name('schedule');
    Route::view('/availability', 'pages.availability')->name('availability');
    Route::get('/equipment', function (): View {
        return view('pages.equipment', [
            'equipment' => Equipment::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get(),
            'canManageEquipment' => in_array(Auth::user()->role, ['admin', 'creator'], true),
        ]);
    })->name('equipment');
    Route::get('/equipment-requests', function (): View {
        $isEquipmentReviewer = in_array(Auth::user()->role, ['admin', 'creator'], true);
        $requests = EquipmentRequest::query()
            ->with(['equipment', 'reservation.room.floor.building'])
            ->when(! $isEquipmentReviewer, fn ($query) => $query->where('requested_by', Auth::id()))
            ->latest()
            ->limit(20)
            ->get();
        $reservations = Reservation::query()
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->where('starts_at', '>', now())
            ->with(['room.floor.building'])
            ->orderBy('starts_at')
            ->get();

        return view('pages.equipment-requests', [
            'requests' => $requests,
            'equipment' => Equipment::query()->where('status', 'active')->orderBy('name')->get(),
            'reservations' => $reservations,
            'isEquipmentReviewer' => $isEquipmentReviewer,
            'canSubmitEquipmentRequests' => Auth::user()->role === 'student',
        ]);
    })->name('equipment-requests');
    Route::get('/reports', [InsightsController::class, 'reports'])->name('reports');
    Route::get('/activity-logs', [InsightsController::class, 'activityLogs'])->name('activity-logs');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::view('/help', 'pages.help')->name('help');
    Route::view('/help/chatbot', 'pages.chatbot')->name('help.chatbot');
    Route::view('/help/reservation-steps', 'pages.reservation-steps')->name('help.reservation-steps');
});
