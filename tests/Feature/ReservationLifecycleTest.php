<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_pending_request_and_cancel_upcoming_reservation(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $startsAt = now()->addDays(4)->startOfHour();
        $endsAt = $startsAt->copy()->addHour();

        $this->actingAs($student)
            ->putJson(route('api.reservations.update', $reservation), [
                'room_id' => $room->id,
                'purpose' => 'Updated student event',
                'organization_name' => 'Campus club',
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
                'notes' => 'Updated notes',
            ])
            ->assertOk()
            ->assertJsonPath('data.purpose', 'Updated student event');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
            'starts_at' => $startsAt->toDateTimeString(),
        ]);

        $this->actingAs($student)
            ->postJson(route('api.reservations.cancel', $reservation), ['cancellation_reason' => 'Event postponed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Event postponed',
        ]);
    }

    public function test_student_can_submit_a_live_request_and_server_rejects_overlaps(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $startsAt = now()->addDays(5)->startOfHour();
        $endsAt = $startsAt->copy()->addHour();
        $attributes = [
            'room_id' => $room->id,
            'purpose' => 'Student assembly',
            'organization_name' => 'Student council',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'notes' => 'Accessibility seating needed',
        ];

        $created = $this->actingAs($student)
            ->postJson(route('api.reservations.store'), $attributes)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $reservationId = $created->json('data.id');
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'user_id' => $student->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $this->actingAs($student)
            ->postJson(route('api.reservations.store'), $attributes)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->actingAs(User::factory()->create(['role' => 'student', 'is_active' => true]))
            ->getJson(route('api.reservations.show', $reservationId))
            ->assertForbidden();
    }

    public function test_reservation_page_connects_form_and_request_list_to_backend(): void
    {
        [$room, $student] = $this->roomAndStudent();

        $this->actingAs($student)
            ->get(route('reservations'))
            ->assertOk()
            ->assertSee(route('api.reservations.store'))
            ->assertSee(route('api.reservations.index'))
            ->assertSee('Your reservations');
    }

    public function test_reviewer_can_approve_and_reject_pending_requests(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reservation = $this->reservation($room, $student);

        $this->actingAs($reviewer)
            ->postJson(route('api.reservations.review', $reservation), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $reviewer->id);

        $rejected = $this->reservation($room, $student, now()->addDays(2), now()->addDays(2)->addHour());

        $this->actingAs($reviewer)
            ->postJson(route('api.reservations.review', $rejected), [
                'status' => 'rejected',
                'decision_reason' => 'Room reserved for campus examination.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_reason', 'Room reserved for campus examination.');

        $this->assertDatabaseHas('reservations', [
            'id' => $rejected->id,
            'reviewed_by' => $reviewer->id,
        ]);
    }

    public function test_review_requires_reviewer_role_and_approval_rechecks_conflicts(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $anotherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'creator', 'is_active' => true]);

        $this->actingAs($student)
            ->postJson(route('api.reservations.review', $reservation), ['status' => 'approved'])
            ->assertForbidden();

        $this->reservation($room, $anotherStudent);

        $this->actingAs($reviewer)
            ->postJson(route('api.reservations.review', $reservation), ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
    }

    public function test_reservation_list_is_scoped_to_students_and_complete_for_reviewers(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $ownReservation = $this->reservation($room, $student);
        $otherReservation = $this->reservation($room, $otherStudent, now()->addDays(2), now()->addDays(2)->addHour());

        $this->actingAs($student)
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownReservation->id);

        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($reviewer)
            ->getJson(route('api.reservations.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $otherReservation->id]);
    }

    public function test_cancelling_reservation_restores_approved_equipment_and_blocks_released_equipment(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $reservation->update(['status' => 'approved']);
        $equipment = Equipment::create(['name' => 'Projector', 'category' => 'AV', 'quantity' => 3, 'available_quantity' => 1, 'status' => 'active']);
        $request = EquipmentRequest::create([
            'equipment_id' => $equipment->id,
            'reservation_id' => $reservation->id,
            'requested_by' => $student->id,
            'quantity' => 2,
            'status' => 'approved',
        ]);

        $this->actingAs($student)->postJson(route('api.reservations.cancel', $reservation), ['cancellation_reason' => 'Event cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 3]);
        $this->assertDatabaseHas('equipment_requests', ['id' => $request->id, 'status' => 'cancelled']);

        $secondReservation = $this->reservation($room, $student, now()->addDays(7));
        $secondReservation->update(['status' => 'approved']);
        $releasedRequest = EquipmentRequest::create([
            'equipment_id' => $equipment->id,
            'reservation_id' => $secondReservation->id,
            'requested_by' => $student->id,
            'quantity' => 1,
            'status' => 'released',
        ]);

        $this->actingAs($student)->postJson(route('api.reservations.cancel', $secondReservation), [])
            ->assertUnprocessable()->assertJsonValidationErrors('reservation');
        $this->assertDatabaseHas('reservations', ['id' => $secondReservation->id, 'status' => 'approved']);
        $this->assertDatabaseHas('equipment_requests', ['id' => $releasedRequest->id, 'status' => 'released']);
    }

    /** @return array{Room, User} */
    private function roomAndStudent(): array
    {
        $floor = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active'])
            ->floors()
            ->create(['number' => 1, 'name' => 'First Floor']);
        $room = $floor->rooms()->create([
            'code' => 'A-101',
            'name' => 'Lecture Room',
            'capacity' => 30,
            'status' => 'active',
        ]);

        return [$room, User::factory()->create(['role' => 'student', 'is_active' => true])];
    }

    private function reservation(Room $room, User $user, ?Carbon $startsAt = null, ?Carbon $endsAt = null): Reservation
    {
        $startsAt ??= now()->addDays(3)->startOfHour();
        $endsAt ??= $startsAt->copy()->addHour();

        return $room->reservations()->create([
            'user_id' => $user->id,
            'purpose' => 'Student event',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
        ]);
    }
}
