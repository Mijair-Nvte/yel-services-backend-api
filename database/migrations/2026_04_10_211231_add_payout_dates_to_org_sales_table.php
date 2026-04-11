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
        // Fecha en la que el administrador decide que se pagará la comisión
        $table->date('seller_payout_date')->nullable()->after('commission_status');
        $table->date('processor_payout_date')->nullable()->after('processor_commission_status');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            //
        });
    }
};
