<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationConflictService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly UserNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Reservation
    {
        return DB::transaction(function () use ($user, $attributes): Reservation {
            $room = Room::query()->lockForUpdate()->findOrFail($attributes['room_id']);
            $startsAt = $attributes['starts_at'] instanceof CarbonInterface ? $attributes['starts_at'] : Carbon::parse($attributes['starts_at']);
            $endsAt = $attributes['ends_at'] instanceof CarbonInterface ? $attributes['ends_at'] : Carbon::parse($attributes['ends_at']);

            if ($room->status !== 'active') {
                throw ValidationException::withMessages(['room_id' => 'This room is not currently available.']);
            }

            if (! $startsAt instanceof CarbonInterface || ! $endsAt instanceof CarbonInterface || $startsAt->greaterThanOrEqualTo($endsAt)) {
                throw ValidationException::withMessages(['ends_at' => 'The reservation must end after it starts.']);
            }

            $this->ensureRoomCanBeReserved($room, $startsAt, $endsAt);

            $reservation = $room->reservations()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'user_id' => $user->getKey(),
                'status' => 'pending',
            ]);

            $this->activityLogger->record($user, 'reservation.requested', $reservation, ['status' => 'pending']);
            User::query()->whereIn('role', ['admin', 'creator'])->where('is_active', true)->get()
                ->each(fn (User $reviewer) => $this->notifications->send($reviewer, 'reservation.requested', 'Reservation needs review', $user->name.' requested '.$room->name.'.', ['reservation_id' => $reservation->id]));

            return $reservation;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, Reservation $reservation, array $attributes): Reservation
    {
        return DB::transaction(function () use ($user, $reservation, $attributes): Reservation {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());

            abort_unless(
                $lockedReservation->user_id === $user->getKey()
                    && $lockedReservation->status === 'pending'
                    && $lockedReservation->starts_at->isFuture(),
                403,
            );

            $targetRoom = Room::query()->lockForUpdate()->findOrFail($attributes['room_id']);
            $startsAt = Carbon::parse($attributes['starts_at']);
            $endsAt = Carbon::parse($attributes['ends_at']);

            if ($targetRoom->status !== 'active') {
                throw ValidationException::withMessages(['room_id' => 'This room is not currently available.']);
            }

            $this->ensureRoomCanBeReserved($targetRoom, $startsAt, $endsAt, $lockedReservation);

            $lockedReservation->update([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->activityLogger->record($user, 'reservation.updated', $lockedReservation, ['status' => $lockedReservation->status]);

            return $lockedReservation->fresh(['room.floor.building']);
        });
    }

    public function cancel(Reservation $reservation, ?string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason): Reservation {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());

            if (! in_array($lockedReservation->status, ['pending', 'approved'], true) || $lockedReservation->starts_at->isPast()) {
                throw ValidationException::withMessages(['reservation' => 'Only upcoming pending or approved reservations can be cancelled.']);
            }

            $equipmentRequests = EquipmentRequest::query()->where('reservation_id', $lockedReservation->getKey())
                ->whereIn('status', ['pending', 'approved', 'released'])->lockForUpdate()->get();

            if ($equipmentRequests->contains(fn (EquipmentRequest $equipmentRequest): bool => $equipmentRequest->status === 'released')) {
                throw ValidationException::withMessages(['reservation' => 'Return released equipment before cancelling this reservation.']);
            }

            foreach ($equipmentRequests as $equipmentRequest) {
                if ($equipmentRequest->status === 'approved') {
                    $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipmentRequest->equipment_id);

                    if ($equipment->available_quantity + $equipmentRequest->quantity > $equipment->quantity) {
                        throw ValidationException::withMessages(['reservation' => 'The associated equipment allocation could not be safely restored. Contact an administrator.']);
                    }

                    $equipment->increment('available_quantity', $equipmentRequest->quantity);
                }

                $equipmentRequest->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Room reservation cancelled'.($reason ? ': '.$reason : '.'),
                ]);
            }

            $lockedReservation->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);

            $this->activityLogger->record(Auth::user(), 'reservation.cancelled', $lockedReservation, ['reason' => $reason]);
            $this->notifications->send($lockedReservation->user, 'reservation.cancelled', 'Reservation cancelled', 'Your reservation for '.$lockedReservation->room->name.' was cancelled.', ['reservation_id' => $lockedReservation->id]);

            return $lockedReservation->fresh(['room.floor.building']);
        });
    }

    public function decide(User $reviewer, Reservation $reservation, string $status, ?string $reason): Reservation
    {
        return DB::transaction(function () use ($reviewer, $reservation, $status, $reason): Reservation {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());

            if ($lockedReservation->status !== 'pending' || $lockedReservation->starts_at->isPast()) {
                throw ValidationException::withMessages(['reservation' => 'Only upcoming pending reservations can be reviewed.']);
            }

            if ($status === 'approved') {
                $room = Room::query()->lockForUpdate()->findOrFail($lockedReservation->room_id);

                if ($room->status !== 'active') {
                    throw ValidationException::withMessages(['room_id' => 'This room is not currently available.']);
                }

                $this->ensureRoomCanBeReserved(
                    $room,
                    $lockedReservation->starts_at,
                    $lockedReservation->ends_at,
                    $lockedReservation,
                );
            }

            $lockedReservation->update([
                'status' => $status,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'decision_reason' => $reason,
                'approved_by' => $status === 'approved' ? $reviewer->getKey() : null,
                'approved_at' => $status === 'approved' ? now() : null,
            ]);

            $this->activityLogger->record($reviewer, 'reservation.'.$status, $lockedReservation, ['reason' => $reason]);
            $this->notifications->send($lockedReservation->user, 'reservation.'.$status, 'Reservation '.($status === 'approved' ? 'approved' : 'rejected'), 'Your reservation for '.$lockedReservation->room->name.' was '.$status.'.', ['reservation_id' => $lockedReservation->id]);

            return $lockedReservation->fresh(['room.floor.building', 'user']);
        });
    }

    private function ensureRoomCanBeReserved(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?Reservation $except = null,
    ): void {
        $conflictsWithReservation = $room->reservations()
            ->whereIn('status', ['pending', 'approved'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->exists();

        if ($conflictsWithReservation) {
            throw ValidationException::withMessages(['room_id' => 'This room is already reserved during the selected time.']);
        }

        $conflictsWithMaintenance = $room->maintenancePeriods()
            ->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflictsWithMaintenance) {
            throw ValidationException::withMessages(['room_id' => 'This room is unavailable because of a maintenance period.']);
        }
    }
}
