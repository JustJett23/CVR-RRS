<?php

namespace App\Models;

use Database\Factories\EquipmentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRequest extends Model
{
    /** @use HasFactory<EquipmentRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'reservation_id',
        'requested_by',
        'quantity',
        'status',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'released_by',
        'released_at',
        'returned_by',
        'returned_at',
        'return_notes',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reviewed_at' => 'datetime',
            'released_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
