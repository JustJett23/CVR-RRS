<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentRequestWorkflowService
{
    /**
     * @param  array{equipment_id: int, quantity: int, notes?: ?string}  $attributes
     */
    public function create(User $requester, Reservation $reservation, array $attributes): EquipmentRequest
    {
        abort_unless($reservation->user_id === $requester->getKey(), 403);

        if ($reservation->status !== 'approved') {
            throw ValidationException::withMessages(['reservation_id' => 'Equipment can only be requested for an approved reservation.']);
        }

        return DB::transaction(function () use ($requester, $reservation, $attributes): EquipmentRequest {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($attributes['equipment_id']);

            if ($equipment->status !== 'active') {
                throw ValidationException::withMessages(['equipment_id' => 'This equipment is not available for requests.']);
            }

            $alreadyRequested = EquipmentRequest::query()
                ->where('reservation_id', $reservation->getKey())
                ->where('equipment_id', $equipment->getKey())
                ->whereIn('status', ['pending', 'approved', 'released'])
                ->exists();

            if ($alreadyRequested) {
                throw ValidationException::withMessages(['equipment_id' => 'This equipment is already requested for this reservation.']);
            }

            if ($attributes['quantity'] > $equipment->available_quantity) {
                throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds the currently available stock.']);
            }

            return EquipmentRequest::query()->create([
                ...$attributes,
                'reservation_id' => $reservation->getKey(),
                'requested_by' => $requester->getKey(),
                'status' => 'pending',
            ]);
        }, 3);
    }

    public function approve(EquipmentRequest $equipmentRequest): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest): EquipmentRequest {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($request->equipment_id);
            $this->ensureStatus($request, 'pending');

            if ($equipment->status !== 'active' || $equipment->available_quantity < $request->quantity) {
                throw ValidationException::withMessages(['equipment_request' => 'There is not enough available equipment to approve this request.']);
            }

            $equipment->decrement('available_quantity', $request->quantity);
            $request->update(['status' => 'approved']);

            return $request->fresh(['equipment', 'reservation.room', 'requester']);
        }, 3);
    }

    public function reject(EquipmentRequest $equipmentRequest): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest): EquipmentRequest {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $this->ensureStatus($request, 'pending');
            $request->update(['status' => 'rejected']);

            return $request->fresh(['equipment', 'reservation.room', 'requester']);
        }, 3);
    }

    public function release(EquipmentRequest $equipmentRequest): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest): EquipmentRequest {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $this->ensureStatus($request, 'approved');
            $request->update(['status' => 'released']);

            return $request->fresh(['equipment', 'reservation.room', 'requester']);
        }, 3);
    }

    public function return(EquipmentRequest $equipmentRequest): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest): EquipmentRequest {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($request->equipment_id);
            $this->ensureStatus($request, 'released');
            $equipment->increment('available_quantity', $request->quantity);
            $request->update(['status' => 'returned']);

            return $request->fresh(['equipment', 'reservation.room', 'requester']);
        }, 3);
    }

    private function ensureStatus(EquipmentRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw ValidationException::withMessages(['equipment_request' => 'This equipment request can no longer be changed from its current status.']);
        }
    }
}
