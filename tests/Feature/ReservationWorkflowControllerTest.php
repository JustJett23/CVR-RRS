<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationWorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_submission_persists_a_pending_reservation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $room = $this->createRoom();
        $startsAt = now()->addDay()->setTime(9, 0);
        $endsAt = $startsAt->copy()->addHour();

        $response = $this->actingAs($student)->postJson(route('api.reservations.store'), [
            'room_id' => $room->id,
            'purpose' => 'Student organization meeting',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.user_id', $student->id);
        $this->assertDatabaseHas('reservations', [
            'room_id' => $room->id,
            'user_id' => $student->id,
            'status' => 'pending',
            'purpose' => 'Student organization meeting',
        ]);
    }

    public function test_submission_rejects_a_conflicting_reservation_without_creating_another(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $existingRequester = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $room = $this->createRoom();
        $startsAt = now()->addDay()->setTime(9, 0);
        $endsAt = $startsAt->copy()->addHour();
        $this->createPendingReservation($room, $existingRequester, $startsAt, $endsAt);

        $this->actingAs($student)
            ->postJson(route('api.reservations.store'), [
                'room_id' => $room->id,
                'purpose' => 'Conflicting meeting',
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_staff_approval_persists_approved_status_and_reviewer(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $room = $this->createRoom();
        $reservation = $this->createPendingReservation($room, $student);

        $this->actingAs($reviewer)
            ->post(route('reservations.approve', $reservation))
            ->assertRedirect(route('reservations'))
            ->assertSessionHas('status', 'Reservation approved.');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'approved',
            'approved_by' => $reviewer->id,
        ]);
    }

    public function test_student_cannot_approve_a_reservation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $requester = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reservation = $this->createPendingReservation($this->createRoom(), $requester);

        $this->actingAs($student)
            ->post(route('reservations.approve', $reservation))
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
    }

    public function test_reservation_owner_can_cancel_a_pending_request(): void
    {
        $requester = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reservation = $this->createPendingReservation($this->createRoom(), $requester);

        $this->actingAs($requester)
            ->post(route('reservations.cancel', $reservation))
            ->assertRedirect(route('reservations'))
            ->assertSessionHas('status', 'Pending reservation cancelled.');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'cancelled']);
    }

    public function test_another_student_cannot_cancel_a_pending_request(): void
    {
        $requester = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reservation = $this->createPendingReservation($this->createRoom(), $requester);

        $this->actingAs($otherStudent)
            ->post(route('reservations.cancel', $reservation))
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
    }

    private function createRoom(): Room
    {
        $building = Building::query()->create(['code' => 'NPC', 'name' => 'NPC Main Building', 'status' => 'active']);
        $floor = $building->floors()->create(['number' => 1, 'name' => 'First Floor']);

        return Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'RM-101',
            'name' => 'Meeting Room',
            'type' => 'Meeting Room',
            'capacity' => 20,
            'status' => 'active',
        ]);
    }

    private function createPendingReservation(Room $room, User $requester, ?Carbon $startsAt = null, ?Carbon $endsAt = null): Reservation
    {
        $startsAt ??= now()->addDay()->setTime(9, 0);
        $endsAt ??= $startsAt->copy()->addHour();

        return Reservation::query()->create([
            'room_id' => $room->id,
            'user_id' => $requester->id,
            'purpose' => 'Scheduled meeting',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
        ]);
    }
}
