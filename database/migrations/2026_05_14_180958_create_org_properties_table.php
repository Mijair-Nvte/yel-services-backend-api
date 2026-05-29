<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_properties', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // El partner inversionista

            $table->string('title'); // Ej: Houston, TX - 3/2 Single Family
            $table->string('portfolio_type')->nullable(); // Ej: Portafolio Start Loan
            $table->decimal('investment_amount', 12, 2)->default(0.00); // Ej: 285000.00
            $table->string('cash_flow_status')->nullable(); // Ej: Flujo positivo
            $table->string('image_path')->nullable();

            $table->enum('status', ['prospect', 'in_progress', 'closed'])->default('prospect');
            $table->timestamp('closed_at')->nullable(); // Fecha de cierre (detona el nivel)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_properties');
    }
};
