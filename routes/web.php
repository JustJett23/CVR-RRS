<?php

use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Models\Room;
use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', function (): RedirectResponse {
    return redirect()->route(Auth::check() ? 'dashboard' : 'landing');
})->name('home');
Route::view('/landing', 'landing')->name('landing');
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
    });

    Route::view('/rooms', 'pages.rooms')->name('rooms');
    Route::get('/reservations', function (): View {
        $rooms = Room::query()
            ->select(['id', 'floor_id', 'code', 'name'])
            ->where('status', 'active')
            ->with('floor:id,number')
            ->orderBy('code')
            ->get();

        return view('pages.reservations', ['rooms' => $rooms]);
    })->name('reservations');
    Route::view('/schedule', 'pages.schedule')->name('schedule');
    Route::view('/availability', 'pages.availability')->name('availability');
    Route::get('/equipment', function (): View {
        return view('pages.equipment', [
            'equipment' => Equipment::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get(),
        ]);
    })->name('equipment');
    Route::get('/equipment-requests', function (): View {
        $requests = EquipmentRequest::query()
            ->with(['equipment', 'reservation.room'])
            ->where('requested_by', Auth::id())
            ->latest()
            ->limit(20)
            ->get();

        return view('pages.equipment-requests', ['requests' => $requests]);
    })->name('equipment-requests');
    Route::view('/reports', 'pages.reports')->name('reports');
    Route::view('/activity-logs', 'pages.activity-logs')->name('activity-logs');
    Route::view('/settings', 'pages.settings')->name('settings');
    Route::view('/help', 'pages.help')->name('help');
    Route::view('/help/chatbot', 'pages.chatbot')->name('help.chatbot');
    Route::view('/help/reservation-steps', 'pages.reservation-steps')->name('help.reservation-steps');
});
