<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $building = Building::query()->updateOrCreate(
            ['code' => 'NPC-MAIN'],
            ['name' => 'Navotas Polytechnic College Main Building', 'status' => 'active'],
        );

        $building->floors()->with('rooms')->get()->each(fn ($floor) => $floor->rooms()->withTrashed()->forceDelete());

        foreach ([1, 2] as $number) {
            $floor = $building->floors()->updateOrCreate(
                ['number' => $number],
                ['name' => 'Floor '.$number],
            );

            $roomNumbers = $number === 1 ? [1, 2, 3] : [1, 2];
            foreach ($roomNumbers as $roomNumber) {
                $floor->rooms()->updateOrCreate(
                    ['code' => 'RM-'.$number.sprintf('%02d', $roomNumber)],
                    [
                        'name' => 'Learning Room '.$number.'-'.$roomNumber,
                        'type' => 'classroom',
                        'capacity' => 30,
                        'status' => 'active',
                    ],
                );
            }
        }
    }
}
