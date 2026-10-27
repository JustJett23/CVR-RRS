<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    /**
     * Create or refresh the role-based demo accounts.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->accounts() as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => self::DEMO_PASSWORD,
                    'role' => $account['role'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{name: string, email: string, role: string}>
     */
    private function accounts(): array
    {
        $accounts = [
            ['name' => 'Demo Creator', 'email' => 'creator@cve-demo.example.com', 'role' => 'creator'],
            ['name' => 'Demo Admin', 'email' => 'admin@cve-demo.example.com', 'role' => 'admin'],
            ['name' => 'Demo Organization Leader', 'email' => 'orgleader@cve-demo.example.com', 'role' => 'org_leader'],
            ['name' => 'Demo Student', 'email' => 'student@cve-demo.example.com', 'role' => 'student'],
        ];

        return $accounts;
    }
}
