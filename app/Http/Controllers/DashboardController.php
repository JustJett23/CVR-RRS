<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('welcome', [
            'roomsByFloor' => $this->sampleRoomsByFloor(),
        ]);
    }

    public function rooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor' => ['required', 'integer', 'in:1,2'],
        ]);

        return response()->json([
            'data' => $this->sampleRoomsByFloor()[(int) $validated['floor']],
            'meta' => [
                'floor' => (int) $validated['floor'],
                'sample' => true,
            ],
        ]);
    }

    /**
     * The room inventory is illustrative until campus data is connected.
     *
     * @return array<int, array<int, array{id: string, name: string, capacity: int, equipment: string, status: string, next: string}>>
     */
    private function sampleRoomsByFloor(): array
    {
        return [
            1 => [
                ['id' => '101', 'name' => 'Lecture hall', 'capacity' => 80, 'equipment' => 'Projector, audio', 'status' => 'available', 'next' => 'Available now'],
                ['id' => '102', 'name' => 'Registrar office', 'capacity' => 18, 'equipment' => 'Workstations', 'status' => 'occupied', 'next' => '11:30 AM'],
                ['id' => '103', 'name' => 'Student commons', 'capacity' => 42, 'equipment' => 'Display, seating', 'status' => 'reserved', 'next' => '1:00 PM'],
                ['id' => '104', 'name' => 'Learning resource room', 'capacity' => 28, 'equipment' => 'Computers, display', 'status' => 'unavailable', 'next' => 'Schedule unavailable'],
                ['id' => '105', 'name' => 'Conference room', 'capacity' => 16, 'equipment' => 'Projector', 'status' => 'maintenance', 'next' => 'Maintenance review'],
            ],
            2 => [
                ['id' => '204', 'name' => 'Seminar room', 'capacity' => 40, 'equipment' => 'Projector, audio', 'status' => 'available', 'next' => 'Available now'],
                ['id' => '201', 'name' => 'Lecture room', 'capacity' => 36, 'equipment' => 'Projector, whiteboard', 'status' => 'occupied', 'next' => '11:00 AM'],
                ['id' => '202', 'name' => 'Discussion room', 'capacity' => 24, 'equipment' => 'Display, whiteboard', 'status' => 'reserved', 'next' => '1:30 PM'],
                ['id' => '203', 'name' => 'Faculty room', 'capacity' => 18, 'equipment' => 'Workstations', 'status' => 'maintenance', 'next' => 'Maintenance review'],
                ['id' => '205', 'name' => 'Computer lab', 'capacity' => 32, 'equipment' => '32 computers', 'status' => 'unavailable', 'next' => 'Schedule unavailable'],
            ],
        ];
    }
}
