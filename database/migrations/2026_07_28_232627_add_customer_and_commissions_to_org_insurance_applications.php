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
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            // 1. Relación con el Cliente Final
            // Lo hacemos nullable temporalmente para no romper los registros existentes
            $table->foreignId('org_customer_id')
                  ->nullable()
                  ->after('org_company_id') // Ajusta el 'after' según el orden de tus columnas si lo deseas
                  ->constrained('org_customers')
                  ->onDelete('cascade');

            // 2. Campos de Comisión
            $table->decimal('commission_amount', 10, 2)->default(0.00)->after('status');
            $table->enum('commission_status', ['pending', 'paid', 'not_applicable'])->default('not_applicable')->after('commission_amount');
            $table->date('seller_payout_date')->nullable()->after('commission_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            $table->dropForeign(['org_customer_id']);
            
            $table->dropColumn([
                'org_customer_id',
                'commission_amount',
                'commission_status',
                'seller_payout_date'
            ]);
        });
    }
};