<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        $building = Building::query()->where('code', 'NPC-MAIN')->firstOrFail();

        return view('pages.rooms', [
            'building' => $building,
            'floors' => $building->floors()->orderBy('number')->get(['id', 'number', 'name']),
            'managedRooms' => Room::query()
                ->whereHas('floor', fn ($query) => $query->where('building_id', $building->id))
                ->with('floor:id,number,name')
                ->orderBy('floor_id')
                ->orderBy('code')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoomManagement($request->user());
        $building = Building::query()->where('code', 'NPC-MAIN')->firstOrFail();
        $data = $this->validatedRoomData($request, $building->id);

        Room::query()->create($data);

        return to_route('rooms')->with('status', 'Room added to the campus inventory.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeRoomManagement($request->user());
        $building = Building::query()->where('code', 'NPC-MAIN')->firstOrFail();
        abort_unless($room->floor()->where('building_id', $building->id)->exists(), 404);
        $data = $this->validatedRoomData($request, $building->id, $room);

        $room->update($data);

        return to_route('rooms')->with('status', 'Room configuration saved.');
    }

    /** @return array{floor_id: int, code: string, name: string, type: ?string, capacity: int, status: string, description: ?string} */
    private function validatedRoomData(Request $request, int $buildingId, ?Room $room = null): array
    {
        $floorId = $request->integer('floor_id');
        $data = $request->validate([
            'floor_id' => ['required', 'integer', Rule::exists('floors', 'id')->where('building_id', $buildingId)],
            'code' => ['required', 'string', 'max:32', Rule::unique('rooms', 'code')->where('floor_id', $floorId)->ignore($room?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:48'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', 'string', 'in:active,unavailable,maintenance'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['floor_id'] = (int) $data['floor_id'];
        $data['capacity'] = (int) $data['capacity'];

        return $data;
    }

    private function authorizeRoomManagement(?User $user): void
    {
        abort_unless($user instanceof User && $user->is_active && $user->role === 'admin', 403);
    }
}
