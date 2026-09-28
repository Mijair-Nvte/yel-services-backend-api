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
            // 1. Renombramos la columna existente para estandarizarla sin perder datos
            $table->renameColumn('loan_type', 'loan_purpose');
        });

        // Necesitamos un segundo bloque Schema para agregar columnas DESPUÉS de la renombrada
        Schema::table('org_loan_applications', function (Blueprint $table) {
            // 2. Nuevas Columnas
            $table->string('loan_program', 50)->nullable()->after('loan_purpose')
                ->comment('dscr, conventional, fha, va, bank_statement, hard_money');

            $table->string('occupancy_type', 50)->nullable()->after('loan_program')
                ->comment('primary_residence, second_home, investment');

            $table->boolean('is_first_time_buyer')->default(false)->after('occupancy_type');

            // ⚡ 3. Índices de Rendimiento
            $table->index('loan_purpose');
            $table->index('loan_program');
            $table->index('occupancy_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_loan_applications', function (Blueprint $table) {
            $table->dropIndex(['loan_purpose']);
            $table->dropIndex(['loan_program']);
            $table->dropIndex(['occupancy_type']);

            $table->dropColumn([
                'loan_program',
                'occupancy_type',
                'is_first_time_buyer',
            ]);
        });

        Schema::table('org_loan_applications', function (Blueprint $table) {
            // Revertimos el nombre original si hacemos un rollback
            $table->renameColumn('loan_purpose', 'loan_type');
        });
    }
};