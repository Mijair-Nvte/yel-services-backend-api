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
        Schema::create('org_bank_accounts', function (Blueprint $table) {
            $table->id();
            
            // Identificador público seguro
            $table->string('uid', 50)->unique(); 
            
            // Relación con el Tenant (Empresa)
            $table->foreignId('org_company_id')
                  ->constrained('org_companies')
                  ->cascadeOnDelete();
                  
            // Relación con el usuario que registró la cuenta (Auditoría)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Datos bancarios
            $table->string('bank_name'); // Ej. BBVA, Banamex
            $table->string('account_holder_name'); // Titular de la cuenta
            $table->string('account_number')->nullable(); 
            $table->string('clabe', 18)->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // Protege el historial de pagos futuros
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_bank_accounts');
    }
};