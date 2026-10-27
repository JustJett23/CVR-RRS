<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupExistingStudentTest extends TestCase
{
    public function test_existing_student_lookup_tolerates_one_ocr_name_error_and_returns_saved_contacts(): void
    {
        User::factory()->create([
            'name' => 'Jet Riggr B. Carlos',
            'student_number' => '250154',
            'course' => 'Associate in Information System',
            'contact_number' => '09670566433',
            'emergency_contact' => 'CAROLINE M. BARTOLAY · 09756326393',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->postJson(route('login.lookup-id'), [
            'scanned_name' => 'Jet Rigor B. Carlos',
            'scanned_student_number' => '250154',
            'scanned_course' => 'Associate in Information System',
        ])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('contact_number', '09670566433')
            ->assertJsonPath('emergency_contact', 'CAROLINE M. BARTOLAY · 09756326393');
    }

    public function test_existing_student_lookup_rejects_a_different_name_even_with_matching_number_and_course(): void
    {
        User::factory()->create([
            'name' => 'Jet Riggr B. Carlos',
            'student_number' => '250154',
            'course' => 'Associate in Information System',
            'contact_number' => '09670566433',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->postJson(route('login.lookup-id'), [
            'scanned_name' => 'Jane Smith',
            'scanned_student_number' => '250154',
            'scanned_course' => 'Associate in Information System',
        ])
            ->assertOk()
            ->assertJsonPath('matched', false)
            ->assertJsonMissing(['contact_number' => '09670566433']);
    }
}
