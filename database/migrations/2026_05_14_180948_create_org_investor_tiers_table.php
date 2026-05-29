<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_investor_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            $table->string('name'); // Ej: Start, Plus, Elite
            $table->integer('min_properties')->default(0);
            $table->integer('max_properties')->nullable(); // null indicaría "sin límite" o "5+"
            $table->decimal('discount_percentage', 5, 2)->default(0.00); // Ej: 10.00, 20.00
            $table->json('features')->nullable(); // Beneficios visuales
            $table->string('color_theme')->nullable(); // Colores para el dashboard
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_investor_tiers');
    }
};