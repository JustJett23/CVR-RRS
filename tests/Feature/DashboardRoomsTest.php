<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_dashboard_renders_its_database_room_workspace(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $room = $this->createRoom();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Campus Venue Engine')
            ->assertSee('Workspace snapshot')
            ->assertSee('Room availability')
            ->assertSee('value="all" selected', false)
            ->assertSee('data-icon="door-closed"', false)
            ->assertSee('data-icon="door-open"', false)
            ->assertSee('data-icon="list-checks"', false)
            ->assertSee('data-floor-data-endpoint="'.route('dashboard.floor-plan-data').'"', false)
            ->assertSee($room->code);
    }

    public function test_room_endpoint_returns_persisted_rooms_for_requested_floor(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $room = $this->createRoom();

        $this->actingAs($user)
            ->getJson(route('dashboard.rooms', ['floor' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.floor', 1)
            ->assertJsonPath('meta.sample', false)
            ->assertJsonPath('data.0.id', $room->code);
    }

    public function test_room_endpoint_returns_no_rooms_for_an_existing_empty_floor(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $building = Building::query()->create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active']);
        $building->floors()->create(['number' => 1, 'name' => 'Floor 1']);
        $building->floors()->create(['number' => 3, 'name' => 'Floor 3']);

        $this->actingAs($user)
            ->getJson(route('dashboard.rooms', ['floor' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.floor', 3)
            ->assertJsonPath('data', []);
    }

    private function createRoom(): Room
    {
        $building = Building::query()->create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active']);
        $floor = $building->floors()->create(['number' => 1, 'name' => 'Floor 1']);

        return Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'RM-101',
            'name' => 'Test Room',
            'capacity' => 20,
            'status' => 'active',
        ]);
    }
}
