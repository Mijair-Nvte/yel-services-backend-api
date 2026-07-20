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
    Schema::table('org_company_partners', function (Blueprint $table) {
        // Guardamos el nivel actual para consultas ultra rápidas
        $table->foreignId('org_partner_tier_id')->nullable()->constrained('org_partner_tiers')->nullOnDelete();
        
        // Guardamos el acumulado histórico para no tener que hacer SUM()
        $table->decimal('lifetime_sales_volume', 12, 2)->default(0.00);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_company_partners', function (Blueprint $table) {
            //
        });
    }
};
