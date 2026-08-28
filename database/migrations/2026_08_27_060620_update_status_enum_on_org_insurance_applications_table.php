<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- ¡Muy importante para que DB:: funcione!

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Actualizamos cualquier registro existente
        DB::table('org_insurance_applications')
            ->whereNotIn('status', ['Open', 'Lost', 'Won', 'Abandon'])
            ->update(['status' => 'Open']);

        // 2. Limita el ENUM exclusivamente a los 4 estados requeridos
        DB::statement("ALTER TABLE `org_insurance_applications` MODIFY COLUMN `status` ENUM('Open', 'Lost', 'Won', 'Abandon') NOT NULL DEFAULT 'Open';");

        // 3. ESTA ES LA SOLUCIÓN: Envolver la columna en Schema::table
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            $table->timestamp('won_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            $table->dropColumn('won_at');
        });

        // Reversión al conjunto anterior de estados por si necesitas hacer rollback
        DB::statement("ALTER TABLE `org_insurance_applications` MODIFY COLUMN `status` ENUM('pending', 'reviewing', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending';");
    }
};