<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_report_calculates_booked_duration_on_sqlite(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $building = Building::query()->create(['code' => 'TEST', 'name' => 'Test Building', 'status' => 'active']);
        $floor = $building->floors()->create(['number' => 1, 'name' => 'First Floor']);
        $room = $floor->rooms()->create([
            'code' => 'A-101',
            'name' => 'Meeting Room',
            'capacity' => 20,
            'status' => 'active',
        ]);
        $startsAt = now()->subHours(2)->startOfMinute();

        Reservation::query()->create([
            'room_id' => $room->getKey(),
            'user_id' => User::factory()->create(['role' => 'student'])->getKey(),
            'purpose' => 'Report duration test',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(90),
            'status' => 'approved',
        ]);

        $this->actingAs($staff)
            ->get(route('reports'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['bookedHours'] === '1.5')
            ->assertViewHas('roomUsage', fn ($roomUsage): bool => (int) $roomUsage->first()->reservations_count === 1);
    }

    public function test_students_cannot_view_staff_reports(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'student', 'is_active' => true]))
            ->get(route('reports'))
            ->assertForbidden();
    }
}
