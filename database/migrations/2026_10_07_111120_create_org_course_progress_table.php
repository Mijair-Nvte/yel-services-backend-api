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
        Schema::create('org_course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('org_course_lesson_id')->constrained('org_course_lessons')->cascadeOnDelete();
            
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->integer('watch_time_seconds')->default(0); 
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            // Un usuario solo tiene un registro de progreso por cada lección
            $table->unique(['user_id', 'org_course_lesson_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_course_progress');
    }
};