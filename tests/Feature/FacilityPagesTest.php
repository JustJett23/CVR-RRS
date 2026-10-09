<?php

namespace Tests\Feature;

use Tests\TestCase;

class FacilityPagesTest extends TestCase
{
    public function test_each_facility_page_renders_its_heading_and_active_navigation(): void
    {
        $pages = [
            ['dashboard', 'Campus overview'],
            ['floor-plan', '3D Floor Plan'],
            ['rooms', 'Room Directory'],
            ['reservations', 'Reservations'],
            ['schedule', 'Schedule Timeline'],
            ['availability', 'Room Availability'],
            ['equipment', 'Equipment'],
            ['equipment-requests', 'Equipment Requests'],
            ['reports', 'Insights &amp; Reports'],
            ['activity-logs', 'Activity Logs'],
            ['settings', 'Settings'],
            ['help', 'Help &amp; Support'],
        ];

        foreach ($pages as [$route, $heading]) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($heading, false)
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_floor_plan_renders_selected_floor_and_interactive_controls(): void
    {
        $this->get(route('floor-plan', ['floor' => 2]))
            ->assertOk()
            ->assertSee('value="2" selected', false)
            ->assertSee('data-map-action="rotate"', false)
            ->assertSee('data-map-action="zoom-in"', false)
            ->assertSee('data-map-action="fullscreen"', false);
    }
}
