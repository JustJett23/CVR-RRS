<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentRequestWorkflowService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly UserNotificationService $notifications,
    ) {}

    /** @param array{equipment_id: int, reservation_id: int, quantity: int, notes?: ?string} $attributes */
    public function create(User $user, array $attributes): EquipmentRequest
    {
        return DB::transaction(function () use ($user, $attributes): EquipmentRequest {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($attributes['reservation_id']);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($attributes['equipment_id']);
            $this->ensureReservationIsEligible($reservation, $user);
            $this->ensureEquipmentIsAvailableForRequest($equipment, $attributes['quantity']);

            $equipmentRequest = EquipmentRequest::query()->create([
                ...$attributes,
                'requested_by' => $user->getKey(),
                'status' => 'pending',
            ])->load(['equipment', 'reservation.room']);

            $this->activityLogger->record($user, 'equipment_request.requested', $equipmentRequest, ['quantity' => $equipmentRequest->quantity]);
            User::query()->whereIn('role', ['admin', 'creator'])->where('is_active', true)->get()
                ->each(fn (User $reviewer) => $this->notifications->send($reviewer, 'equipment_request.requested', 'Equipment request needs review', $user->name.' requested '.$equipment->name.'.', ['equipment_request_id' => $equipmentRequest->id]));

            return $equipmentRequest;
        }, 3);
    }

    /** @param array{equipment_id: int, reservation_id: int, quantity: int, notes?: ?string} $attributes */
    public function update(User $user, EquipmentRequest $equipmentRequest, array $attributes): EquipmentRequest
    {
        return DB::transaction(function () use ($user, $equipmentRequest, $attributes): EquipmentRequest {
            $lockedRequest = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());

            abort_unless(
                $lockedRequest->requested_by === $user->getKey() && $lockedRequest->status === 'pending',
                403,
            );

            $reservation = Reservation::query()->lockForUpdate()->findOrFail($attributes['reservation_id']);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($attributes['equipment_id']);
            $this->ensureReservationIsEligible($reservation, $user);
            $this->ensureEquipmentIsAvailableForRequest($equipment, $attributes['quantity']);

            $lockedRequest->update($attributes);
            $this->activityLogger->record($user, 'equipment_request.updated', $lockedRequest, ['quantity' => $lockedRequest->quantity]);

            return $lockedRequest->fresh(['equipment', 'reservation.room']);
        }, 3);
    }

    public function cancel(EquipmentRequest $equipmentRequest, ?string $reason): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest, $reason): EquipmentRequest {
            $lockedRequest = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($lockedRequest->reservation_id);

            if (! in_array($lockedRequest->status, ['pending', 'approved'], true) || $reservation->starts_at->isPast()) {
                throw ValidationException::withMessages(['equipment_request' => 'Only upcoming pending or approved equipment requests can be cancelled.']);
            }

            if ($lockedRequest->status === 'approved') {
                $equipment = Equipment::query()->lockForUpdate()->findOrFail($lockedRequest->equipment_id);
                $this->restoreAvailableQuantity($equipment, $lockedRequest->quantity);
            }

            $lockedRequest->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);
            $this->activityLogger->record(Auth::user(), 'equipment_request.cancelled', $lockedRequest, ['reason' => $reason]);

            return $lockedRequest->fresh(['equipment', 'reservation.room']);
        }, 3);
    }

    public function review(User $reviewer, EquipmentRequest $equipmentRequest, string $status, ?string $reason): EquipmentRequest
    {
        return DB::transaction(function () use ($reviewer, $equipmentRequest, $status, $reason): EquipmentRequest {
            $lockedRequest = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($lockedRequest->reservation_id);

            if ($lockedRequest->status !== 'pending' || ! $reservation->starts_at->isFuture()) {
                throw ValidationException::withMessages(['equipment_request' => 'Only upcoming pending equipment requests can be reviewed.']);
            }

            if ($reservation->status !== 'approved') {
                throw ValidationException::withMessages(['reservation_id' => 'Equipment can only be approved for an approved room reservation.']);
            }

            if ($status === 'approved') {
                $equipment = Equipment::query()->lockForUpdate()->findOrFail($lockedRequest->equipment_id);

                if ($equipment->status !== 'active' || $equipment->available_quantity < $lockedRequest->quantity) {
                    throw ValidationException::withMessages(['equipment_id' => 'Not enough active equipment is available to approve this request.']);
                }

                $equipment->decrement('available_quantity', $lockedRequest->quantity);
            }

            $lockedRequest->update([
                'status' => $status,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'decision_reason' => $reason,
            ]);
            $this->activityLogger->record($reviewer, 'equipment_request.'.$status, $lockedRequest, ['reason' => $reason]);
            $this->notifications->send($lockedRequest->requester, 'equipment_request.'.$status, 'Equipment request '.($status === 'approved' ? 'approved' : 'rejected'), 'Your request for '.$lockedRequest->equipment->name.' was '.$status.'.', ['equipment_request_id' => $lockedRequest->id]);

            return $lockedRequest->fresh(['equipment', 'reservation.room', 'requester:id,name,student_number']);
        }, 3);
    }

    public function release(User $reviewer, EquipmentRequest $equipmentRequest): EquipmentRequest
    {
        return DB::transaction(function () use ($reviewer, $equipmentRequest): EquipmentRequest {
            $lockedRequest = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($lockedRequest->reservation_id);

            if ($lockedRequest->status !== 'approved' || $reservation->status !== 'approved' || $reservation->ends_at->isPast()) {
                throw ValidationException::withMessages(['equipment_request' => 'Only approved equipment for a current or upcoming room reservation can be released.']);
            }

            $lockedRequest->update([
                'status' => 'released',
                'released_by' => $reviewer->getKey(),
                'released_at' => now(),
            ]);
            $this->activityLogger->record($reviewer, 'equipment_request.released', $lockedRequest);
            $this->notifications->send($lockedRequest->requester, 'equipment_request.released', 'Equipment released', $lockedRequest->equipment->name.' is ready for your reservation.', ['equipment_request_id' => $lockedRequest->id]);

            return $lockedRequest->fresh(['equipment', 'reservation.room', 'requester:id,name,student_number']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function returnEquipment(User $reviewer, EquipmentRequest $equipmentRequest, array $attributes): EquipmentRequest
    {
        return DB::transaction(function () use ($reviewer, $equipmentRequest, $attributes): EquipmentRequest {
            $lockedRequest = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->getKey());

            if ($lockedRequest->status !== 'released') {
                throw ValidationException::withMessages(['equipment_request' => 'Only released equipment can be marked returned.']);
            }

            $equipment = Equipment::query()->lockForUpdate()->findOrFail($lockedRequest->equipment_id);
            $this->restoreAvailableQuantity($equipment, $lockedRequest->quantity);
            $lockedRequest->update([
                'status' => 'returned',
                'returned_by' => $reviewer->getKey(),
                'returned_at' => now(),
                'return_notes' => $attributes['return_notes'] ?? null,
            ]);
            $this->activityLogger->record($reviewer, 'equipment_request.returned', $lockedRequest, ['notes' => $attributes['return_notes'] ?? null]);
            $this->notifications->send($lockedRequest->requester, 'equipment_request.returned', 'Equipment return recorded', 'The return of '.$lockedRequest->equipment->name.' was recorded.', ['equipment_request_id' => $lockedRequest->id]);

            return $lockedRequest->fresh(['equipment', 'reservation.room', 'requester:id,name,student_number']);
        }, 3);
    }

    private function ensureReservationIsEligible(Reservation $reservation, User $user): void
    {
        if (
            $reservation->user_id !== $user->getKey()
            || $reservation->status !== 'approved'
            || ! $reservation->starts_at->isFuture()
        ) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Choose one of your own upcoming approved room reservations.',
            ]);
        }
    }

    private function ensureEquipmentIsAvailableForRequest(Equipment $equipment, int $quantity): void
    {
        if ($equipment->status !== 'active' || $equipment->available_quantity < $quantity) {
            throw ValidationException::withMessages([
                'equipment_id' => 'Choose active equipment with enough units currently available.',
            ]);
        }
    }

    private function restoreAvailableQuantity(Equipment $equipment, int $quantity): void
    {
        if ($equipment->available_quantity + $quantity > $equipment->quantity) {
            throw ValidationException::withMessages([
                'equipment_id' => 'Returning these units would exceed the recorded inventory total.',
            ]);
        }

        $equipment->increment('available_quantity', $quantity);
    }
}
