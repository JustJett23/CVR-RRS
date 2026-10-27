<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Services\ActivityLogger;
use App\Services\ReservationConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->is_active === true && $user->role !== 'student', 403);
        $isStaff = $user?->is_active && in_array($user->role, ['admin', 'creator'], true);
        $reservations = Reservation::query()
            ->with(['room.floor.building', 'user'])
            ->when(! $isStaff, fn ($query) => $query->where('user_id', $user?->getKey()))
            ->latest('starts_at')
            ->paginate(20);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReservationRequest $request, ReservationConflictService $service, ActivityLogger $activityLogger): JsonResponse
    {
        $reservation = $service->create($request->user(), $request->validated());
        $activityLogger->record($request->user(), 'reservation.created', $reservation, [
            'status' => $reservation->status,
            'room_id' => $reservation->room_id,
        ], $request->ip());

        return response()->json(['data' => $reservation->load('room.floor.building')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation): JsonResponse
    {
        abort_unless(Auth::user()?->is_active === true && Auth::user()?->role !== 'student', 403);
        abort_unless(
            $reservation->user_id === Auth::id() || in_array(Auth::user()?->role, ['admin', 'creator'], true),
            403,
        );

        return response()->json(['data' => $reservation->load('room.floor.building')]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
