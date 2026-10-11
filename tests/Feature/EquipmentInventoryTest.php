<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_can_create_and_update_inventory_without_losing_allocated_stock(): void
    {
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $created = $this->actingAs($reviewer)
            ->postJson(route('api.equipment.store'), [
                'name' => 'Portable projector',
                'category' => 'Audio & visual',
                'quantity' => 5,
                'status' => 'active',
                'description' => 'HDMI projector',
            ])
            ->assertCreated()
            ->assertJsonPath('data.available_quantity', 5);

        $equipment = Equipment::query()->findOrFail($created->json('data.id'));
        $equipment->update(['available_quantity' => 3]);

        $this->actingAs($reviewer)
            ->putJson(route('api.equipment.update', $equipment), [
                'name' => 'Portable projector',
                'category' => 'Audio & visual',
                'quantity' => 8,
                'status' => 'active',
                'description' => 'HDMI projector',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', 8)
            ->assertJsonPath('data.available_quantity', 6);

        $this->actingAs($reviewer)
            ->putJson(route('api.equipment.update', $equipment), [
                'name' => 'Portable projector',
                'category' => 'Audio & visual',
                'quantity' => 1,
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_students_can_read_active_inventory_but_cannot_manage_it(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $active = Equipment::create([
            'name' => 'Microphone',
            'category' => 'Audio & visual',
            'quantity' => 3,
            'available_quantity' => 3,
            'status' => 'active',
        ]);
        $inactive = Equipment::create([
            'name' => 'Retired camera',
            'category' => 'Audio & visual',
            'quantity' => 1,
            'available_quantity' => 1,
            'status' => 'inactive',
        ]);

        $this->actingAs($student)
            ->getJson(route('api.equipment.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['name' => $inactive->name]);

        $this->actingAs($student)
            ->getJson(route('api.equipment.show', $inactive))
            ->assertNotFound();

        $this->actingAs($student)
            ->postJson(route('api.equipment.store'), [
                'name' => 'Unauthorized equipment',
                'quantity' => 1,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_equipment_page_exposes_database_management_only_to_reviewers(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'creator', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('equipment'))
            ->assertOk()
            ->assertDontSee('Manage inventory');

        $this->actingAs($reviewer)
            ->get(route('equipment'))
            ->assertOk()
            ->assertSee('Manage inventory')
            ->assertSee(route('api.equipment.index'))
            ->assertSee(route('api.equipment.store'));

    }
}
