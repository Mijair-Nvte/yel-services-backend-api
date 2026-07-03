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
        Schema::create('org_loan_applications', function (Blueprint $table) {
            $table->id();
            
            // Identificador único al estilo del sistema (ej. loa_XJ8S9...)
            $table->string('uid')->unique(); 
            
            // Relaciones base (Multi-tenant y autoría)
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // El afiliado/cliente que lo crea
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // Admin asignado para revisión
            
            // Datos del Formulario
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone', 30);
            $table->date('applicant_dob');
            $table->string('applicant_address');
            $table->string('applicant_state', 50)->nullable();
            
            // Categoría y Monto
            $table->string('loan_type');
            $table->decimal('estimated_amount', 12, 2)->default(0);
            
            // Estado y Tracking
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            
            // Campo metadata para hacerla 100% escalable a futuro sin romper la DB
            $table->json('metadata')->nullable();
            
            // Timestamps y Soft Deletes
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_loan_applications');
    }
};