<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Siguiendo tu estándar de identificadores únicos
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Clasificación del feedback
            $table->enum('type', ['bug', 'feature_request', 'general_comment', 'help'])->default('general_comment');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending');
            
            // Contenido
            $table->string('title');
            $table->text('description');
            
            // Opcional: Si quieres permitir que suban una captura de pantalla, 
            // relacionándolo con tu tabla 'documents' actual.
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes(); // deleted_at como en tus otras tablas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_feedbacks');
    }
};