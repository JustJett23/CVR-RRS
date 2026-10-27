<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyIdContactNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_international_and_local_contact_numbers_are_saved_in_canonical_format(): void
    {
        $contactNumbers = [
            '+63 917 123 4567' => '09171234567',
            '0063 (917) 123-4567' => '09171234567',
            '9171234567' => '09171234567',
            '0917-123-4567' => '09171234567',
        ];

        $studentNumber = 250731;

        foreach ($contactNumbers as $input => $expected) {

            $this->postJson(route('login.verify-id'), [
                'scanned_name' => 'Test Student',
                'scanned_course' => 'Associate in Information System',
                'scanned_student_number' => $studentNumber,
                'scanned_contact' => $input,
                'emergency_contact' => 'Emergency Person 09181234567',
                'organization_id' => 'ICS',
            ])
                ->assertOk()
                ->assertJsonPath('authenticated', true)
                ->assertJsonPath('redirect', route('dashboard.student', absolute: false));

            $this->assertSame($expected, User::query()->where('student_number', $studentNumber)->value('contact_number'));
            $this->assertAuthenticated();
            $studentNumber++;
        }
    }
}
