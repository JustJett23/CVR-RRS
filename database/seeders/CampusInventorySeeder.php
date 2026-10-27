<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class CampusInventorySeeder extends Seeder
{
    private const RESERVABLE_CATEGORIES = [
        'classroom', 'computer_laboratory', 'science_laboratory', 'laboratory',
        'assembly', 'study', 'library', 'sports', 'seating', 'event_space', 'garden', 'food_service',
    ];

    public function run(): void
    {
        $manifestPath = database_path('seeders/data/NPC_Floorplan_Base_2F_3F_4F_Roof.json');
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;

        if (! is_array($manifest['floors'] ?? null)) {
            throw new RuntimeException('The NPC room inventory manifest is missing or invalid.');
        }

        $building = Building::query()->updateOrCreate(
            ['code' => 'NPC-MAIN'],
            ['name' => 'NPC Main Building', 'status' => 'active', 'description' => 'Room inventory transcribed from the NPC floor plans.'],
        );

        foreach ($manifest['floors'] as $floorIndex => $floorData) {
            $floor = Floor::query()->updateOrCreate(
                ['building_id' => $building->id, 'number' => $floorIndex + 1],
                [
                    'name' => $floorData['name'],
                    'plan_asset' => match ($floorData['level']) {
                        '2F' => 'assets/NPC_Floor_2f.glb',
                        '3F' => 'assets/NPC_Floor_3f.glb',
                        '4F' => 'assets/NPC_Floor_4f.glb',
                        'RF' => 'assets/NPC_Floor_roof.glb',
                        default => null,
                    },
                ],
            );

            $usedCodes = [];

            foreach ($floorData['spaces'] as $spaceIndex => $space) {
                $sourceCode = $space['room_id'] ?? null;
                $baseCode = $sourceCode ?: sprintf('%s-X%03d', $floorData['level'], $spaceIndex + 1);
                $code = $baseCode;
                $suffix = 2;

                while (isset($usedCodes[$code])) {
                    $code = sprintf('%s-%02d', $baseCode, $suffix++);
                }
                $usedCodes[$code] = true;

                $category = $space['category'];
                $reservable = in_array($category, self::RESERVABLE_CATEGORIES, true);

                Room::query()->updateOrCreate(
                    ['floor_id' => $floor->id, 'code' => $code],
                    [
                        'name' => $space['name'],
                        'type' => Str::limit(Str::replace('_', ' ', $category), 48, ''),
                        'capacity' => $space['capacity_persons'] ?? 0,
                        'status' => $reservable ? 'active' : 'unavailable',
                        'description' => $reservable ? 'Reservable campus space.' : 'Campus space recorded from the floor plan; not available for room reservations.',
                        'metadata' => [
                            'source_room_id' => $sourceCode,
                            'source_floor' => $floorData['level'],
                            'source_category' => $category,
                            'source_page' => $floorData['source_page'] ?? null,
                            'reservable' => $reservable,
                            'model_asset' => 'assets/npc_campus_room_stack.glb',
                            'model_level' => $floorData['level'] === 'RF' ? 'ROOF' : $floorData['level'],
                        ],
                    ],
                );
            }
        }
    }
}
