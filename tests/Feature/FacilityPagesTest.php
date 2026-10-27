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
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

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
            ['profile', 'My profile'],
            ['notifications.index', 'Notification center'],
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
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->get(route('floor-plan', ['floor' => 2]))
            ->assertOk()
            ->assertSee('value="2" selected', false)
            ->assertSee('data-map-action="rotate"', false)
            ->assertSee('data-map-action="zoom-in"', false)
            ->assertSee('data-map-action="fullscreen"', false);
    }

    public function test_profile_page_renders_qr_image_placeholder_for_account_token(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'qr_token' => 'CVE-TEST-QR-TOKEN',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('data-qr-code', false)
            ->assertSee('data-qr-value="CVE-TEST-QR-TOKEN"', false)
            ->assertSee('data-qr-download', false);
    }

    public function test_notification_center_shows_full_message_body(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $fullMessage = 'Full notification details '.str_repeat('campus update content ', 120);
        $user->userNotifications()->create([
            'type' => 'reservation_status',
            'title' => 'Reservation update',
            'message' => $fullMessage,
            'data' => ['room_code' => 'R-201', 'decision_note' => 'Reviewed in full.'],
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($fullMessage)
            ->assertSee('R-201')
            ->assertSee('Reviewed in full.');
    }

    public function test_home_route_opens_landing_page_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Campus Venue Engine');
    }
}
