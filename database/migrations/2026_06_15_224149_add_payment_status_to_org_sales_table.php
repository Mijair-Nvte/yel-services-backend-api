<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            // Añadimos el estado del pago justo después del monto total
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                  ->default('pending')
                  ->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};