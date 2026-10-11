<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_facility_page_renders_its_heading_and_active_navigation(): void
    {
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
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
            $response = $this->actingAs($reviewer)->get(route($route))
                ->assertOk()
                ->assertSee($heading, false)
                ->assertSee('aria-current="page"', false);

            if ($route === 'dashboard') {
                $response->assertSee('<title>Dashboard · CVR</title>', false)
                    ->assertSee('name="robots" content="noindex, nofollow"', false);
            }
        }
    }

    public function test_floor_plan_renders_selected_floor_and_interactive_controls(): void
    {
        $reviewer = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($reviewer)->get(route('floor-plan', ['floor' => 2]))
            ->assertOk()
            ->assertSee('value="2" selected', false)
            ->assertSee('data-map-action="rotate"', false)
            ->assertSee('data-map-action="zoom-in"', false)
            ->assertSee('data-map-action="fullscreen"', false);
    }

    public function test_public_entry_and_help_pages_render(): void
    {
        $this->get(route('landing'))->assertOk()->assertSee('Make space for');
        $this->get(route('login'))->assertOk()->assertSee('Welcome');

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('help.chatbot'))->assertOk()->assertSee('Ask about rooms or reservations');
        $this->actingAs($student)->get(route('help.reservation-steps'))->assertOk()->assertSee('From room search to submission');
        $this->actingAs($student)->get(route('reservations'))->assertOk()->assertSee('data-default-duration="120"', false);
    }
}
