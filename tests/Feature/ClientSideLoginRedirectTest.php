<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientSideLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_same_origin_form_and_dashboard_paths(): void
    {
        $this->get(route('login', absolute: false))
            ->assertOk()
            ->assertSee('action="/login"', false)
            ->assertSee('data-verify-url="/login/verify-id"', false)
            ->assertSee('data-dashboard-url="/dashboard"', false);
    }

    public function test_email_login_authenticates_and_returns_json_without_a_redirect_target(): void
    {
        $user = User::factory()->create([
            'email' => 'student@navotaspolytechniccollege.edu.ph',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->postJson(route('login.store', absolute: false), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonMissingPath('redirect');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_email_login_returns_json_errors_without_a_redirect(): void
    {
        $this->postJson(route('login.store', absolute: false), [
            'email' => 'student@navotaspolytechniccollege.edu.ph',
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingPath('redirect');

        $this->assertGuest();
    }
}