<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationConflictService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Reservation
    {
        return DB::transaction(function () use ($user, $attributes): Reservation {
            $room = Room::query()->lockForUpdate()->findOrFail($attributes['room_id']);
            $startsAt = $attributes['starts_at'] instanceof CarbonInterface ? $attributes['starts_at'] : Carbon::parse($attributes['starts_at']);
            $endsAt = $attributes['ends_at'] instanceof CarbonInterface ? $attributes['ends_at'] : Carbon::parse($attributes['ends_at']);

            if (! $room->trashed() && $room->status !== 'active') {
                throw ValidationException::withMessages(['room_id' => 'This room is not currently available.']);
            }

            if (! $startsAt instanceof CarbonInterface || ! $endsAt instanceof CarbonInterface || $startsAt->greaterThanOrEqualTo($endsAt)) {
                throw ValidationException::withMessages(['ends_at' => 'The reservation must end after it starts.']);
            }

            $conflict = $room->reservations()->whereIn('status', ['pending', 'approved'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
            $maintenance = $room->maintenancePeriods()->whereIn('status', ['scheduled', 'active'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['room_id' => 'This room is already reserved during the selected time.']);
            }

            if ($maintenance) {
                throw ValidationException::withMessages(['room_id' => 'This room is unavailable because of a maintenance period.']);
            }

            return $room->reservations()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'user_id' => $user->getKey(),
                'status' => 'pending',
            ]);
        });
    }
}
