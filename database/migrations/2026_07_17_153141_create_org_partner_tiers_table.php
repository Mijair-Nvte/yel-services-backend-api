<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('org_partner_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();

            $table->string('name'); // Ej: Bronce, Plata, Oro
            $table->decimal('min_sales_volume', 12, 2)->default(0.00); // Ej: 0
            $table->decimal('max_sales_volume', 12, 2)->nullable(); // Ej: 500
            $table->decimal('commission_percentage', 5, 2)->default(0.00); // Ej: 8.00, 20.00

            $table->json('features')->nullable(); // Por si también les das beneficios extra
            $table->string('color_theme')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_partner_tiers');
    }
};
