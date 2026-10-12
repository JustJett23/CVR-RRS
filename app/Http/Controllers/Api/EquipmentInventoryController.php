<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentInventoryRequest;
use App\Http\Requests\UpdateEquipmentInventoryRequest;
use App\Models\Equipment;
use App\Services\ActivityLogger;
use App\Services\EquipmentInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentInventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $equipment = Equipment::query()
            ->when(! in_array($request->user()?->role, ['admin', 'creator'], true), fn ($query) => $query->where('status', 'active'))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $equipment]);
    }

    public function show(Request $request, Equipment $equipment): JsonResponse
    {
        abort_unless(
            $equipment->status === 'active'
                || in_array($request->user()?->role, ['admin', 'creator'], true),
            404,
        );

        return response()->json(['data' => $equipment]);
    }

    public function store(StoreEquipmentInventoryRequest $request, EquipmentInventoryService $service, ActivityLogger $activityLogger): JsonResponse
    {
        $equipment = $service->create($request->validated());
        $activityLogger->record($request->user(), 'equipment_inventory.created', $equipment, ['quantity' => $equipment->quantity]);

        return response()->json(['data' => $equipment], 201);
    }

    public function update(UpdateEquipmentInventoryRequest $request, Equipment $equipment, EquipmentInventoryService $service, ActivityLogger $activityLogger): JsonResponse
    {
        $updated = $service->update($equipment, $request->validated());
        $activityLogger->record($request->user(), 'equipment_inventory.updated', $updated, ['status' => $updated->status]);

        return response()->json(['data' => $updated]);
    }
}
