<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Actualizamos cualquier registro existente que tenga un estado diferente a los 4 nuevos
        DB::table('org_loan_applications')
            ->whereNotIn('status', ['Open', 'Lost', 'Won', 'Abandon'])
            ->update(['status' => 'Open']);

        // 2. Limita el ENUM exclusivamente a los 4 estados requeridos
        DB::statement("ALTER TABLE `org_loan_applications` MODIFY COLUMN `status` ENUM('Open', 'Lost', 'Won', 'Abandon') NOT NULL DEFAULT 'Open';");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversión al conjunto anterior de estados por si necesitas hacer rollback
        DB::statement("ALTER TABLE `org_loan_applications` MODIFY COLUMN `status` ENUM('pending', 'reviewing', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending';");
    }
};