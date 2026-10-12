<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomDirectoryController extends Controller
{
    public function index(): View
    {
        $buildings = Building::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $floors = Floor::query()
            ->whereHas('building', fn ($query) => $query->where('status', 'active'))
            ->with('building:id,name')
            ->orderBy('building_id')
            ->orderBy('number')
            ->get(['id', 'building_id', 'number', 'name']);

        $types = Room::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('pages.rooms', compact('buildings', 'floors', 'types'));
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'building_id' => ['sometimes', 'nullable', 'integer', 'exists:buildings,id'],
            'floor_id' => ['sometimes', 'nullable', 'integer', 'exists:floors,id'],
            'type' => ['sometimes', 'nullable', 'string', 'max:48'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['sometimes', 'nullable', Rule::in(['available', 'occupied', 'reserved', 'maintenance', 'unavailable'])],
        ]);

        $now = now();
        $windowEnd = $now->copy()->addMinute();

        $rooms = Room::query()
            ->select(['id', 'floor_id', 'code', 'name', 'type', 'capacity', 'status', 'metadata'])
            ->with([
                'floor:id,building_id,number,name',
                'floor.building:id,name',
                'reservations' => fn ($query) => $query
                    ->select(['id', 'room_id', 'status', 'starts_at', 'ends_at'])
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('ends_at', '>', $now)
                    ->orderBy('starts_at'),
                'maintenancePeriods' => fn ($query) => $query
                    ->select(['id', 'room_id', 'status', 'starts_at', 'ends_at'])
                    ->whereIn('status', ['scheduled', 'active'])
                    ->where('ends_at', '>', $now)
                    ->orderBy('starts_at'),
            ])
            ->whereHas('floor.building', fn ($query) => $query->where('status', 'active'))
            ->when(isset($filters['building_id']), fn ($query) => $query->whereHas('floor', fn ($floorQuery) => $floorQuery->where('building_id', $filters['building_id'])))
            ->when(isset($filters['floor_id']), fn ($query) => $query->where('floor_id', $filters['floor_id']))
            ->when(isset($filters['type']) && $filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['capacity']), fn ($query) => $query->where('capacity', '>=', $filters['capacity']))
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room): array => $this->presentRoom($room, $now, $windowEnd))
            ->when(isset($filters['status']), fn ($rooms) => $rooms->where('status', $filters['status']))
            ->values();

        return response()->json([
            'data' => $rooms,
            'meta' => [
                'count' => $rooms->count(),
                'filters' => $filters,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array{id: string, name: string, type: ?string, capacity: int, equipment: string, status: string, next: string, building: string, floor: string, floor_number: int}
     */
    private function presentRoom(Room $room, Carbon $now, Carbon $windowEnd): array
    {
        $maintenance = $room->maintenancePeriods->first(
            fn ($period): bool => $period->starts_at < $windowEnd && $period->ends_at > $now,
        );
        $reservation = $room->reservations->first(
            fn ($item): bool => $item->starts_at < $windowEnd && $item->ends_at > $now,
        );

        $status = $room->status;

        if ($status === 'active') {
            $status = $maintenance
                ? 'maintenance'
                : ($reservation?->status === 'approved' && $reservation->starts_at <= $now
                    ? 'occupied'
                    : ($reservation ? 'reserved' : 'available'));
        } elseif (! in_array($status, ['occupied', 'reserved', 'maintenance', 'unavailable'], true)) {
            $status = 'unavailable';
        }

        $nextReservation = $room->reservations->first(fn ($item): bool => $item->starts_at > $now);
        $nextAvailability = $reservation && $reservation->ends_at > $now
            ? $reservation->ends_at->format('g:i A')
            : ($nextReservation?->starts_at?->format('g:i A') ?? 'Available now');

        return [
            'id' => $room->code,
            'name' => $room->name,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'equipment' => data_get($room->metadata, 'equipment', 'Campus equipment inventory'),
            'status' => $status,
            'next' => $nextAvailability,
            'building' => $room->floor->building->name,
            'floor' => $room->floor->name,
            'floor_number' => $room->floor->number,
        ];
    }
}
