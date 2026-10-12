<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    protected $fillable = ['name', 'category', 'quantity', 'available_quantity', 'status', 'description'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'available_quantity' => 'integer'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EquipmentRequest::class);
    }
}
