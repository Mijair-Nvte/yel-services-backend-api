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
        Schema::create('org_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('org_course_id')->constrained('org_courses')->cascadeOnDelete();
            
            $table->string('source')->default('free'); // free, manual, revenuecat
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // Útil para suscripciones que vencen
            
            $table->timestamps();
            
            // Un usuario solo puede tener un registro de inscripción por curso
            $table->unique(['user_id', 'org_course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_course_enrollments');
    }
};