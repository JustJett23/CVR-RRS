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

    protected $fillable = ['equipment_id', 'reservation_id', 'requested_by', 'quantity', 'status', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
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
}
