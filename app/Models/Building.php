<?php

namespace App\Models;

use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'status', 'description'];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }
}
