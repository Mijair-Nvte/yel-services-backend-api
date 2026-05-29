<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            // Se agrega nullable para no afectar las ventas anteriores que no tenían servicio asociado
            $table->foreignId('org_service_id')->nullable()->after('product_name')->constrained('org_services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            $table->dropForeign(['org_service_id']);
            $table->dropColumn('org_service_id');
        });
    }
};