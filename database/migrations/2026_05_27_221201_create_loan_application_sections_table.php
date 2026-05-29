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
        Schema::create('loan_application_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_application_id');
            $table->integer('section_id'); // ID de la sección (1 al 8)
            
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->json('data'); // Respuestas en formato JSON nativo
            
            $table->timestamps();

            // Asegura que no se dupliquen secciones para una misma solicitud
            $table->unique(['loan_application_id', 'section_id']);
            
            // Relación con la tabla principal
            $table->foreign('loan_application_id')
                  ->references('id')
                  ->on('loan_applications')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_application_sections');
    }
};