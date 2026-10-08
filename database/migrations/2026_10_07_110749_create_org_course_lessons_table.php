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
        Schema::create('org_course_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_course_module_id')->constrained('org_course_modules')->cascadeOnDelete();
            
            $table->string('title');
            $table->text('description')->nullable();
            
            // Bucket Privado
            $table->string('video_path')->nullable(); 
            $table->integer('duration_seconds')->default(0);
            
            // Lógica de negocio
            $table->boolean('is_free_preview')->default(false); // Si es true, permite verlo sin estar inscrito
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Para PDFs, links o material de apoyo a futuro
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_course_lessons');
    }
};