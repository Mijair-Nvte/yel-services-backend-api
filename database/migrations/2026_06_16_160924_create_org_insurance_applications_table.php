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
        Schema::create('org_insurance_applications', function (Blueprint $table) {
            $table->id();
            // Identificador público único para las URLs y la API (ej. ins_01KSTNHX...)
            $table->string('uid')->unique()->index();
            
            // Llaves foráneas integradas con el esquema multitenant
            $table->foreignId('org_company_id')->constrained('org_companies')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Administrador asignado (nullable, por si el equipo crece a futuro)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            
            // Detalles de la solicitud
            $table->string('insurance_type', 100)->nullable(); // Ej: 'general', 'vida', 'medico'
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'completed'])->default('pending');
            
            // Datos del aplicante (Capturados como una instantánea histórica)
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone', 30);
            $table->date('applicant_dob'); // Campo prioritario para la revisión del seguro
            $table->string('applicant_address');
            
            // Columnas de escalabilidad y control administrativo
            $table->json('metadata')->nullable(); // Para añadir cualquier campo extra en el futuro sin alterar la BD
            $table->text('notes')->nullable();     // Notas internas para el administrador encargado
            
            $table->timestamps();
            $table->softDeletes(); // Borrado lógico para auditorías empresariales
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_insurance_applications');
    }
};