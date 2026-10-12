<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyIdContactNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_numbers_are_normalized_before_account_creation(): void
    {
        $contactNumbers = [
            ['+63 917 123 4567', '09171234567'],
            ['0063 (917) 123-4567', '09171234567'],
            ['9171234567', '09171234567'],
            ['0917-123-4567', '09171234567'],
        ];

        foreach ($contactNumbers as $index => [$inputContact, $expectedContact]) {
            $studentNumber = (string) (250731 + $index);

            $this->postJson(route('login.verify-id'), [
                'scanned_name' => 'Test Student',
                'scanned_course' => 'Associate in Information System',
                'scanned_student_number' => $studentNumber,
                'scanned_contact' => $inputContact,
                'emergency_contact' => 'Emergency Person 09181234567',
                'organization_id' => 'ICS',
            ])
                ->assertOk()
                ->assertJsonPath('authenticated', true)
                ->assertJsonMissingPath('redirect');

            $this->assertSame(
                $expectedContact,
                User::query()->where('student_number', $studentNumber)->value('contact_number'),
            );
            $this->assertAuthenticated();
        }
    }

    public function test_invalid_scanned_contact_returns_a_field_validation_error(): void
    {
        $this->postJson(route('login.verify-id'), [
            'scanned_name' => 'Test Student',
            'scanned_course' => 'Associate in Information System',
            'scanned_student_number' => '250735',
            'scanned_contact' => 'not a phone number',
            'emergency_contact' => 'Emergency Person 09181234567',
            'organization_id' => 'ICS',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scanned_contact');
    }
}
