<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_demo_account_can_sign_in_with_its_password(): void
    {
        $user = User::factory()->create([
            'email' => 'creator@cve-demo.example.com',
            'password' => 'password',
            'role' => 'creator',
            'is_active' => true,
        ]);

        $this->postJson(route('login.store', absolute: false), [
            'email' => 'creator@cve-demo.example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('redirect', route('dashboard.creator', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('roleDashboardRoutes')]
    public function test_email_sign_in_redirects_to_matching_role_route(string $role, string $routeName): void
    {
        $email = "{$role}@cve-demo.example.com";
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->postJson(route('login.store', absolute: false), [
            'email' => $email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('redirect', route($routeName, absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->get(route($routeName, absolute: false))->assertOk();
    }

    public static function roleDashboardRoutes(): array
    {
        return [
            'student' => ['student', 'dashboard.student'],
            'organization leader' => ['org_leader', 'dashboard.org-leader'],
            'admin' => ['admin', 'dashboard.admin'],
            'creator' => ['creator', 'dashboard.creator'],
        ];
    }

    public function test_user_cannot_open_another_roles_dashboard_route(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('dashboard.admin', absolute: false))
            ->assertForbidden();
    }

    public function test_database_seeder_creates_demo_account_for_each_supported_role(): void
    {
        $this->seed();

        foreach ([
            ['student@cve-demo.example.com', 'student'],
            ['orgleader@cve-demo.example.com', 'org_leader'],
            ['admin@cve-demo.example.com', 'admin'],
            ['creator@cve-demo.example.com', 'creator'],
        ] as [$email, $role]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($role, $user->role);
            $this->assertTrue(password_verify('password', $user->password));
        }

        $this->assertDatabaseCount('users', 4);
    }

    public function test_demo_account_rejects_an_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'creator@cve-demo.example.com',
            'password' => 'password',
            'role' => 'creator',
            'is_active' => true,
        ]);

        $this->postJson(route('login.store', absolute: false), [
            'email' => 'creator@cve-demo.example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingPath('redirect');

        $this->assertGuest();
    }
}
