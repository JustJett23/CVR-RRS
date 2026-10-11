<?php

namespace App\Models;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = ['room_id', 'user_id', 'approved_by', 'purpose', 'organization_name', 'starts_at', 'ends_at', 'status', 'notes', 'cancellation_reason', 'approved_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function equipmentRequests(): HasMany
    {
        return $this->hasMany(EquipmentRequest::class);
    }

    public function scopeConflicting($query, $startsAt, $endsAt)
    {
        return $query->whereIn('status', ['pending', 'approved'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
