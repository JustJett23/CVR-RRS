<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationAndActivityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_events_create_notifications_and_audit_rows(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $room = $this->room();

        $response = $this->actingAs($student)->postJson(route('api.reservations.store'), [
            'room_id' => $room->id,
            'purpose' => 'Student event',
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->addHours(2)->toDateTimeString(),
        ])->assertCreated();

        $reservationId = $response->json('data.id');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $reviewer->id, 'type' => 'reservation.requested']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $student->id, 'action' => 'reservation.requested', 'subject_id' => $reservationId]);

        $this->actingAs($reviewer)->postJson(route('api.reservations.review', $reservationId), ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'reservation.approved']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $reviewer->id, 'action' => 'reservation.approved', 'subject_id' => $reservationId]);
    }

    public function test_settings_save_profile_and_notification_preferences(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'course' => 'Computer Science',
            'contact_number' => '09170000000',
            'current_password' => 'password',
            'new_password' => 'new-secure-password-123',
            'new_password_confirmation' => 'new-secure-password-123',
            'preferences' => [
                'in_app_notifications' => '0',
                'reservation_updates' => '1',
                'equipment_updates' => '0',
                'default_duration_minutes' => '120',
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'email' => 'updated@example.test']);
        $this->assertSame(false, $user->fresh()->preferences['in_app_notifications']);
        $this->assertTrue(Hash::check('new-secure-password-123', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'account.settings_updated']);
    }

    public function test_settings_reject_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($user)->from(route('settings'))->put(route('settings.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'incorrect-password',
            'new_password' => 'new-secure-password-123',
            'new_password_confirmation' => 'new-secure-password-123',
        ])->assertRedirect(route('settings'))->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reports_and_activity_are_live_and_restricted_to_reviewers(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'creator', 'is_active' => true]);

        $this->actingAs($student)->get(route('reports'))->assertForbidden();
        $this->actingAs($student)->get(route('activity-logs'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('reports'))->assertOk()->assertSee('Reservations by status');
        $this->actingAs($reviewer)->get(route('activity-logs'))->assertOk()->assertSee('Recent activity');
    }

    public function test_help_assistant_answers_from_live_database_and_records_only_intent(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->room();

        $this->actingAs($student)->postJson(route('api.help.ask'), ['question' => 'How many rooms are available?'])
            ->assertOk()
            ->assertJsonPath('answer', '1 rooms are currently free according to the database. This is a snapshot; the server rechecks room and maintenance conflicts when a reservation is submitted.')
            ->assertJsonPath('links.0.label', 'Room availability');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $student->id,
            'action' => 'help.assistant_asked',
            'metadata->intent' => 'availability',
        ]);
    }

    private function room(): Room
    {
        $floor = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active'])
            ->floors()->create(['number' => 1, 'name' => 'First Floor']);

        return $floor->rooms()->create(['code' => 'A-101', 'name' => 'Lecture Room', 'capacity' => 30, 'status' => 'active']);
    }
}
