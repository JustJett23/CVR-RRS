<?php

namespace App\Services;

use App\Models\Equipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentInventoryService
{
    /** @param array{name: string, category?: ?string, quantity: int, status: string, description?: ?string} $attributes */
    public function create(array $attributes): Equipment
    {
        return Equipment::query()->create([
            ...$attributes,
            'available_quantity' => $attributes['quantity'],
        ]);
    }

    /** @param array{name: string, category?: ?string, quantity: int, status: string, description?: ?string} $attributes */
    public function update(Equipment $equipment, array $attributes): Equipment
    {
        return DB::transaction(function () use ($equipment, $attributes): Equipment {
            $lockedEquipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->getKey());
            $allocatedQuantity = $lockedEquipment->quantity - $lockedEquipment->available_quantity;

            if ($attributes['quantity'] < $allocatedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Inventory quantity cannot be lower than the quantity currently allocated to approved or released requests.',
                ]);
            }

            $lockedEquipment->update([
                ...$attributes,
                'available_quantity' => $attributes['quantity'] - $allocatedQuantity,
            ]);

            return $lockedEquipment->refresh();
        });
    }
}
