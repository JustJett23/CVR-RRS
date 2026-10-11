<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->index(['status', 'starts_at'], 'reservations_status_starts_at_index');
            $table->index(['status', 'ends_at'], 'reservations_status_ends_at_index');
        });

        Schema::table('maintenance_periods', function (Blueprint $table): void {
            $table->index(['status', 'ends_at'], 'maintenance_status_ends_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_status_starts_at_index');
            $table->dropIndex('reservations_status_ends_at_index');
        });

        Schema::table('maintenance_periods', function (Blueprint $table): void {
            $table->dropIndex('maintenance_status_ends_at_index');
        });
    }
};
