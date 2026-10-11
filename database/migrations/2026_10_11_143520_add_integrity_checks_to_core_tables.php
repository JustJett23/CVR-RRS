<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_valid_interval CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE equipment ADD CONSTRAINT equipment_valid_quantities CHECK (available_quantity >= 0 AND available_quantity <= quantity)');
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_non_negative_capacity CHECK (capacity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE reservations DROP CHECK reservations_valid_interval');
        DB::statement('ALTER TABLE equipment DROP CHECK equipment_valid_quantities');
        DB::statement('ALTER TABLE rooms DROP CHECK rooms_non_negative_capacity');
    }
};
