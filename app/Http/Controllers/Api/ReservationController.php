<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
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
        $reservations = Reservation::query()->with(['room.floor.building', 'user'])->when($request->user(), fn ($query, $user) => $query->where('user_id', $user->getKey()))->latest('starts_at')->paginate(20);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReservationRequest $request, ReservationConflictService $service): JsonResponse
    {
        $reservation = $service->create($request->user(), $request->validated());

        return response()->json(['data' => $reservation->load('room.floor.building')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation): JsonResponse
    {
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
