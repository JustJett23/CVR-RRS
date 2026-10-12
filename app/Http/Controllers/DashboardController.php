<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePeriod;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $todayStart->copy()->addDay();

        return view('welcome', [
            'roomsByFloor' => $this->roomsByFloor(),
            'metrics' => [
                'totalRooms' => Room::query()->count(),
                'availableRooms' => Room::query()->where('status', 'active')->whereDoesntHave('reservations', fn ($query) => $query->whereIn('status', ['pending', 'approved'])->where('starts_at', '<', $now->copy()->addDay())->where('ends_at', '>', $now))->count(),
                'todayReservations' => Reservation::query()->where('starts_at', '>=', $todayStart)->where('starts_at', '<', $todayEnd)->whereIn('status', ['pending', 'approved'])->count(),
                'pendingRequests' => Reservation::query()->where('status', 'pending')->count(),
                'activeIssues' => MaintenancePeriod::query()->whereIn('status', ['scheduled', 'active'])->where('ends_at', '>', now())->count(),
            ],
        ]);
    }

    public function floorPlan(Request $request): View
    {
        $validated = $request->validate([
            'floor' => ['sometimes', 'integer', 'in:1,2,3'],
        ]);

        $initialFloor = isset($validated['floor']) ? (int) $validated['floor'] : null;

        return view('pages.floor-plan', [
            'roomsByFloor' => $this->roomsByFloor(floorNumber: $initialFloor),
            'initialFloor' => $initialFloor,
        ]);
    }

    public function rooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $floor = (int) $validated['floor'];

        $roomsByFloor = $this->roomsByFloor(floorNumber: $floor);

        return response()->json([
            'data' => $roomsByFloor[$floor] ?? [],
            'meta' => [
                'floor' => $floor,
                'sample' => false,
            ],
        ]);
    }

    public function floorPlanData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor' => ['sometimes', 'integer', 'in:1,2,3'],
            'date' => ['sometimes', 'date'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'date_format:H:i', 'after:starts_at'],
        ]);

        $floor = isset($validated['floor']) ? (int) $validated['floor'] : null;
        $date = Carbon::parse($validated['date'] ?? today()->toDateString())->startOfDay();
        $windowStart = isset($validated['starts_at'])
            ? $date->copy()->setTimeFromTimeString($validated['starts_at'])
            : now();
        $windowEnd = isset($validated['ends_at'])
            ? $date->copy()->setTimeFromTimeString($validated['ends_at'])
            : $windowStart->copy()->addMinutes(1);
        $roomsByFloor = $this->roomsByFloor($windowStart, $windowEnd, $floor);

        return response()->json([
            'data' => $floor === null
                ? collect($roomsByFloor)->flatten(1)->values()->all()
                : ($roomsByFloor[$floor] ?? []),
            'upcoming' => $this->upcomingReservations($floor, $date),
            'meta' => [
                'floor' => $floor,
                'date' => $date->toDateString(),
                'generated_at' => now()->toIso8601String(),
                'sample' => false,
            ],
        ]);
    }

    /**
     * Return room availability derived from persisted rooms and reservations.
     *
     * @return array<int, array<int, array{id: string, name: string, type: ?string, capacity: int, equipment: string, status: string, next: string}>>
     */
    private function roomsByFloor(?Carbon $windowStart = null, ?Carbon $windowEnd = null, ?int $floorNumber = null): array
    {
        $now = now();
        $windowStart ??= $now;
        $windowEnd ??= $now->copy()->addMinute();
        $floorNumbers = $floorNumber === null ? [1, 2, 3] : [$floorNumber];

        return Room::query()
            ->select(['id', 'floor_id', 'code', 'name', 'type', 'capacity', 'status', 'metadata'])
            ->with([
                'floor:id,number',
                'reservations' => fn ($query) => $query
                    ->select(['id', 'room_id', 'status', 'starts_at', 'ends_at'])
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('ends_at', '>', $windowStart)
                    ->orderBy('starts_at'),
                'maintenancePeriods' => fn ($query) => $query
                    ->select(['id', 'room_id', 'status', 'starts_at', 'ends_at'])
                    ->whereIn('status', ['scheduled', 'active'])
                    ->where('ends_at', '>', $windowStart)
                    ->orderBy('starts_at'),
            ])
            ->whereHas('floor', fn ($query) => $query->whereIn('number', $floorNumbers))
            ->orderBy('code')
            ->get()
            ->groupBy('floor.number')
            ->map(fn ($rooms) => $rooms->map(fn (Room $room): array => $this->roomAvailability($room, $windowStart, $windowEnd, $now))->values()->all())
            ->all();
    }

    /**
     * @return array{id: string, name: string, type: ?string, capacity: int, equipment: string, status: string, next: string, floor: int}
     */
    private function roomAvailability(Room $room, Carbon $windowStart, Carbon $windowEnd, Carbon $now): array
    {
        $maintenance = $room->maintenancePeriods->first(fn ($period): bool => $period->starts_at < $windowEnd && $period->ends_at > $windowStart);
        $reservation = $room->reservations->first(fn ($item): bool => $item->starts_at < $windowEnd && $item->ends_at > $windowStart);
        $status = $room->status;

        if ($status === 'active') {
            $status = $maintenance
                ? 'maintenance'
                : ($reservation?->status === 'approved' && $reservation->starts_at <= $now && $reservation->ends_at > $now ? 'occupied' : ($reservation ? 'reserved' : 'available'));
        }

        $equipment = data_get($room->metadata, 'equipment', 'Campus equipment inventory');

        return [
            'id' => $room->code,
            'name' => $room->name,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'equipment' => $equipment,
            'status' => $status,
            'next' => $reservation?->starts_at?->format('g:i A') ?? 'Available now',
            'floor' => $room->floor->number,
        ];
    }

    /**
     * @return array<int, array{id: int, room: string, room_name: string, floor: int, title: string, starts_at: string, ends_at: string, status: string}>
     */
    private function upcomingReservations(?int $floor, Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        return Reservation::query()
            ->select(['id', 'room_id', 'purpose', 'starts_at', 'ends_at', 'status'])
            ->with(['room:id,floor_id,code,name', 'room.floor:id,number'])
            ->whereIn('status', ['pending', 'approved'])
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd)
            ->when($date->isToday(), fn ($query) => $query->where('starts_at', '>=', now()))
            ->when($floor !== null, fn ($query) => $query->whereHas('room.floor', fn ($floorQuery) => $floorQuery->where('number', $floor)))
            ->orderBy('starts_at')
            ->limit(8)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'room' => $reservation->room->code,
                'room_name' => $reservation->room->name,
                'floor' => $reservation->room->floor->number,
                'title' => $reservation->purpose,
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'status' => $reservation->status,
            ])
            ->all();
    }
}
