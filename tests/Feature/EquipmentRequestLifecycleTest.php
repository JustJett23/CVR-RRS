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

class EquipmentRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_edit_and_cancel_pending_request(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $equipment = $this->equipment(4);

        $created = $this->actingAs($student)
            ->postJson(route('api.equipment-requests.store'), [
                'equipment_id' => $equipment->id,
                'reservation_id' => $reservation->id,
                'quantity' => 2,
                'notes' => 'Need a handheld microphone',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $requestId = $created->json('data.id');
        $this->actingAs($student)
            ->putJson(route('api.equipment-requests.update', $requestId), [
                'equipment_id' => $equipment->id,
                'reservation_id' => $reservation->id,
                'quantity' => 1,
                'notes' => 'Only one microphone is needed',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', 1);

        $this->actingAs($student)
            ->postJson(route('api.equipment-requests.cancel', $requestId), ['cancellation_reason' => 'No longer needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 4]);
        $this->assertDatabaseHas('equipment_requests', [
            'id' => $requestId,
            'status' => 'cancelled',
            'cancellation_reason' => 'No longer needed',
        ]);
    }

    public function test_reviewer_approves_releases_and_records_return_with_stock_updates(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $equipment = $this->equipment(5);
        $request = $this->request($equipment, $reservation, $student, 2);
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.review', $request), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $reviewer->id);

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 3]);

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.release', $request))
            ->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.return', $request), ['return_notes' => 'All units returned in working condition'])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.return_notes', 'All units returned in working condition');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 5]);
        $this->assertDatabaseHas('equipment_requests', [
            'id' => $request->id,
            'returned_by' => $reviewer->id,
        ]);
    }

    public function test_reviewer_cannot_approve_unavailable_equipment_and_rejection_needs_reason(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $reservation = $this->reservation($room, $student);
        $equipment = $this->equipment(1);
        $request = $this->request($equipment, $reservation, $student, 2);
        $reviewer = User::factory()->create(['role' => 'creator', 'is_active' => true]);

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.review', $request), ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_id');

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.review', $request), ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_reason');

        $this->actingAs($reviewer)
            ->postJson(route('api.equipment-requests.review', $request), [
                'status' => 'rejected',
                'decision_reason' => 'Inventory is not sufficient for this request.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'available_quantity' => 1]);
    }

    public function test_students_can_only_list_their_own_requests_and_cannot_review(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reservation = $this->reservation($room, $student);
        $otherReservation = $this->reservation($room, $otherStudent, now()->addDays(4));
        $equipment = $this->equipment(8);
        $ownRequest = $this->request($equipment, $reservation, $student, 1);
        $otherRequest = $this->request($equipment, $otherReservation, $otherStudent, 1);

        $this->actingAs($student)
            ->getJson(route('api.equipment-requests.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRequest->id)
            ->assertJsonMissing(['id' => $otherRequest->id]);

        $this->actingAs($student)
            ->postJson(route('api.equipment-requests.review', $ownRequest), ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAs($student)
            ->getJson(route('api.equipment-requests.show', $otherRequest))
            ->assertForbidden();
    }

    public function test_equipment_request_page_wires_student_form_and_reviewer_queue(): void
    {
        [$room, $student] = $this->roomAndStudent();
        $this->reservation($room, $student);
        $this->equipment(3);
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('equipment-requests'))
            ->assertOk()
            ->assertSee(route('api.equipment-requests.store'))
            ->assertSee('Approved reservation');

        $this->actingAs($reviewer)
            ->get(route('equipment-requests'))
            ->assertOk()
            ->assertSee('Reservation requests')
            ->assertSee(route('api.equipment-requests.index'));
    }

    /** @return array{Room, User} */
    private function roomAndStudent(): array
    {
        $floor = Building::create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active'])
            ->floors()
            ->create(['number' => 1, 'name' => 'First Floor']);
        $room = $floor->rooms()->create(['code' => 'A-101', 'name' => 'Lecture Room', 'capacity' => 30, 'status' => 'active']);

        return [$room, User::factory()->create(['role' => 'student', 'is_active' => true])];
    }

    private function reservation(Room $room, User $user, ?Carbon $startsAt = null): Reservation
    {
        $startsAt ??= now()->addDays(3)->startOfHour();

        return $room->reservations()->create([
            'user_id' => $user->id,
            'purpose' => 'Campus event',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => 'approved',
        ]);
    }

    private function equipment(int $quantity): Equipment
    {
        return Equipment::create([
            'name' => 'Handheld microphone',
            'category' => 'Audio & visual',
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'status' => 'active',
        ]);
    }

    private function request(Equipment $equipment, Reservation $reservation, User $requester, int $quantity): EquipmentRequest
    {
        return EquipmentRequest::create([
            'equipment_id' => $equipment->id,
            'reservation_id' => $reservation->id,
            'requested_by' => $requester->id,
            'quantity' => $quantity,
            'status' => 'pending',
        ]);
    }
}
