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
        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 48)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_requests');
        Schema::dropIfExists('equipment');
    }
};
