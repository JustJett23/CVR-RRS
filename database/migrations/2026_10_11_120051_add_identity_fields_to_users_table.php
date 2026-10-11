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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('student_number')->nullable()->unique()->after('name');
            $table->string('course')->nullable()->after('student_number');
            $table->string('contact_number', 30)->nullable()->after('course');
            $table->string('emergency_contact')->nullable()->after('contact_number');
            $table->string('organization_id')->nullable()->index()->after('emergency_contact');
            $table->string('qr_token')->nullable()->unique()->after('organization_id');
            $table->timestamp('onboarding_verified_at')->nullable()->after('qr_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['student_number', 'course', 'contact_number', 'emergency_contact', 'organization_id', 'qr_token', 'onboarding_verified_at']);
        });
    }
};
