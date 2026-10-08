<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardRoomsTest extends TestCase
{
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

    public function test_room_endpoint_returns_sample_rooms_for_requested_floor(): void
    {
        $this->getJson(route('dashboard.rooms', ['floor' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.floor', 1)
            ->assertJsonPath('meta.sample', true)
            ->assertJsonPath('data.0.id', '101');
    }

    public function test_room_endpoint_rejects_unsupported_floor(): void
    {
        $this->getJson(route('dashboard.rooms', ['floor' => 3]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('floor');
    }
}
