<?php

namespace Tests\Feature;

use App\Models\Building;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $floor = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active'])->floors()->create(['number' => 1, 'name' => 'Floor 1']);
        $floor->rooms()->create(['code' => 'RM-101', 'name' => 'Test Room', 'capacity' => 20, 'status' => 'active']);
    }

    public function test_dashboard_renders_reusable_components_and_room_endpoint(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Campus Venue Engine')
            ->assertSee('Workspace snapshot')
            ->assertSee('Room availability')
            ->assertSee('value="1" selected', false)
            ->assertSee('data-icon="door-closed"', false)
            ->assertSee('data-icon="door-open"', false)
            ->assertSee('data-icon="list-checks"', false)
            ->assertSee('data-endpoint="'.route('dashboard.rooms').'"', false);
    }

    public function test_room_endpoint_returns_persisted_rooms_for_requested_floor(): void
    {
        $this->getJson(route('dashboard.rooms', ['floor' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.floor', 1)
            ->assertJsonPath('meta.sample', false)
            ->assertJsonPath('data.0.id', 'RM-101');
    }

    public function test_room_endpoint_accepts_model_floor_without_rooms(): void
    {
        $this->getJson(route('dashboard.rooms', ['floor' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.floor', 3)
            ->assertJsonPath('data', []);
    }
}
