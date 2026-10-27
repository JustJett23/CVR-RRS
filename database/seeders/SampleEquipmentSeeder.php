<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class SampleEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['name' => 'Projector', 'category' => 'Presentation', 'quantity' => 4, 'description' => 'Portable projector for classroom and event presentations.'],
            ['name' => 'HDMI Cord', 'category' => 'Cables', 'quantity' => 12, 'description' => 'HDMI cord for connecting laptops and presentation equipment.'],
            ['name' => 'Wire Extension', 'category' => 'Power', 'quantity' => 8, 'description' => 'Extension cord for temporary room and event setups.'],
        ];

        foreach ($equipment as $item) {
            Equipment::query()->firstOrCreate(
                ['name' => $item['name']],
                [
                    ...$item,
                    'available_quantity' => $item['quantity'],
                    'status' => 'active',
                ],
            );
        }
    }
}
