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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Identificador único (ej: ln_01J...)
            $table->unsignedBigInteger('org_company_id'); // Multi-tenant isolation
            $table->unsignedBigInteger('user_id');        // Solicitante del préstamo
            
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');
            $table->integer('current_step')->default(1);
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            
            $table->timestamps();
            $table->softDeletes(); // Buenas prácticas para auditoría interna

            // Llaves foráneas e índices estructurados
            $table->foreign('org_company_id')->references('id')->on('org_companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['org_company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};