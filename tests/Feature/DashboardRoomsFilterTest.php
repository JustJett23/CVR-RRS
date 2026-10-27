<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoomsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_request_filters_database_rooms_by_building_floor_type_capacity_and_status(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $building = Building::query()->create(['code' => 'NPC', 'name' => 'NPC Main Building', 'status' => 'active']);
        $firstFloor = $building->floors()->create(['number' => 1, 'name' => 'First Floor']);
        $secondFloor = $building->floors()->create(['number' => 2, 'name' => 'Second Floor']);
        $otherBuilding = Building::query()->create(['code' => 'ANNEX', 'name' => 'Annex', 'status' => 'active']);
        $annexFloor = $otherBuilding->floors()->create(['number' => 1, 'name' => 'Annex Floor']);

        Room::query()->create(['floor_id' => $firstFloor->id, 'code' => 'RM-101', 'name' => 'Lecture Hall', 'type' => 'Classroom', 'capacity' => 40, 'status' => 'active']);
        Room::query()->create(['floor_id' => $firstFloor->id, 'code' => 'RM-102', 'name' => 'Small Lab', 'type' => 'Laboratory', 'capacity' => 12, 'status' => 'active']);
        Room::query()->create(['floor_id' => $secondFloor->id, 'code' => 'RM-201', 'name' => 'Second Floor Hall', 'type' => 'Classroom', 'capacity' => 60, 'status' => 'active']);
        Room::query()->create(['floor_id' => $annexFloor->id, 'code' => 'AN-101', 'name' => 'Annex Hall', 'type' => 'Classroom', 'capacity' => 80, 'status' => 'active']);

        $response = $this->actingAs($user)->getJson(route('dashboard.rooms', [
            'building_id' => $building->id,
            'floor' => 1,
            'type' => 'Classroom',
            'min_capacity' => 30,
            'status' => 'available',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'RM-101')
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('meta.building_id', $building->id)
            ->assertJsonPath('meta.floor', 1)
            ->assertJsonPath('meta.types.0', 'Classroom');
    }

    public function test_room_filter_requires_an_authenticated_user(): void
    {
        $this->getJson(route('dashboard.rooms'))
            ->assertUnauthorized();
    }

    public function test_room_filter_rejects_a_negative_minimum_capacity(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('dashboard.rooms', ['min_capacity' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_capacity');
    }
}
