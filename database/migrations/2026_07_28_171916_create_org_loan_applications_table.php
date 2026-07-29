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
        Schema::table('org_loan_applications', function (Blueprint $table) {
            // 1. Relación con el Cliente Final (Centralizado)
            // Usamos org_customer_id para mantener la convención de tu BD y lo hacemos nullable 
            // temporalmente para que no marque error con los registros que ya tienes guardados.
            $table->foreignId('org_customer_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('org_customers')
                  ->onDelete('cascade');

            // 2. Campos de Comisión que habíamos platicado
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
        Schema::table('org_loan_applications', function (Blueprint $table) {
            // Revertir los cambios en caso de un rollback
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