<?php

namespace App\Models;

use Database\Factories\MaintenancePeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePeriod extends Model
{
    /** @use HasFactory<MaintenancePeriodFactory> */
    use HasFactory;

    protected $fillable = ['room_id', 'starts_at', 'ends_at', 'status', 'reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
