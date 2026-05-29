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
    Schema::table('org_sales', function (Blueprint $table) {
        // Lo ponemos después de seller_id para mantener el orden lógico
        $table->string('referral_code')->nullable()->after('seller_id');
        
        // Añadimos un índice para que las búsquedas por código sean ultra rápidas
        $table->index('referral_code');
    });
}

public function down(): void
{
    Schema::table('org_sales', function (Blueprint $table) {
        $table->dropColumn('referral_code');
    });
}
};
