<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomDirectoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_filters_use_live_database_records_and_derived_status(): void
    {
        $user = User::factory()->create();
        $building = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active']);
        $floor = $building->floors()->create(['number' => 1, 'name' => 'First Floor']);

        $availableRoom = $floor->rooms()->create([
            'code' => 'A-101',
            'name' => 'Lecture Room',
            'type' => 'classroom',
            'capacity' => 30,
            'status' => 'active',
            'metadata' => ['equipment' => 'Projector'],
        ]);

        $reservedRoom = $floor->rooms()->create([
            'code' => 'A-102',
            'name' => 'Reserved Lecture Room',
            'type' => 'classroom',
            'capacity' => 25,
            'status' => 'active',
        ]);

        $floor->rooms()->create([
            'code' => 'A-103',
            'name' => 'Small Laboratory',
            'type' => 'laboratory',
            'capacity' => 10,
            'status' => 'active',
        ]);

        $reservedRoom->reservations()->create([
            'user_id' => $user->id,
            'purpose' => 'Upcoming lecture',
            'starts_at' => now()->addSeconds(30),
            'ends_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $filters = [
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'type' => 'classroom',
            'capacity' => 20,
            'status' => 'available',
        ];

        $this->actingAs($user)
            ->getJson(route('api.rooms.index', $filters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $availableRoom->code)
            ->assertJsonPath('data.0.building', 'Test Building')
            ->assertJsonPath('data.0.floor', 'First Floor')
            ->assertJsonPath('data.0.status', 'available');

        $this->actingAs($user)
            ->getJson(route('api.rooms.index', [...$filters, 'status' => 'reserved']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservedRoom->code)
            ->assertJsonPath('data.0.status', 'reserved');
    }

    public function test_directory_page_builds_filter_options_from_database(): void
    {
        $building = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active']);
        $floor = $building->floors()->create(['number' => 1, 'name' => 'First Floor']);
        $floor->rooms()->create([
            'code' => 'A-101',
            'name' => 'Lecture Room',
            'type' => 'classroom',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('rooms'))
            ->assertOk()
            ->assertSee('Test Building')
            ->assertSee('First Floor')
            ->assertSee('classroom')
            ->assertSee(route('api.rooms.index'));
    }

    public function test_directory_api_requires_authentication_and_valid_filters(): void
    {
        $this->getJson(route('api.rooms.index'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.rooms.index', ['capacity' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');
    }
}
