<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationWorkflowService
{
    public function approve(Reservation $reservation, User $reviewer): Reservation
    {
        return DB::transaction(function () use ($reservation, $reviewer): Reservation {
            $room = Room::query()->lockForUpdate()->findOrFail($reservation->room_id);
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());

            $this->ensurePending($lockedReservation);

            if ($room->status !== 'active') {
                throw ValidationException::withMessages(['reservation' => 'This room is no longer active.']);
            }

            $hasConflictingReservation = $room->reservations()
                ->where('reservations.id', '!=', $lockedReservation->getKey())
                ->conflicting($lockedReservation->starts_at, $lockedReservation->ends_at)
                ->exists();
            $hasMaintenanceConflict = $room->maintenancePeriods()
                ->whereIn('status', ['scheduled', 'active'])
                ->where('starts_at', '<', $lockedReservation->ends_at)
                ->where('ends_at', '>', $lockedReservation->starts_at)
                ->exists();

            if ($hasConflictingReservation || $hasMaintenanceConflict) {
                throw ValidationException::withMessages(['reservation' => 'This request now conflicts with another reservation or maintenance period.']);
            }

            $lockedReservation->update([
                'status' => 'approved',
                'approved_by' => $reviewer->getKey(),
                'approved_at' => now(),
            ]);

            return $lockedReservation->fresh(['room.floor.building', 'user']);
        }, 3);
    }

    public function reject(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
            $this->ensurePending($lockedReservation);
            $lockedReservation->update(['status' => 'rejected']);

            return $lockedReservation->fresh(['room.floor.building', 'user']);
        }, 3);
    }

    public function cancel(Reservation $reservation, User $requester): Reservation
    {
        return DB::transaction(function () use ($reservation, $requester): Reservation {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());

            if ($lockedReservation->user_id !== $requester->getKey()) {
                abort(403);
            }

            $this->ensurePending($lockedReservation);
            $lockedReservation->update(['status' => 'cancelled']);

            return $lockedReservation->fresh(['room.floor.building', 'user']);
        }, 3);
    }

    private function ensurePending(Reservation $reservation): void
    {
        if ($reservation->status !== 'pending') {
            throw ValidationException::withMessages(['reservation' => 'Only pending requests can be changed.']);
        }
    }
}
