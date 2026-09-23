<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_customers', function (Blueprint $table) {
            // 1. Agregar el contact_id y su índice
            // Lo ponemos nullable por si creas contactos manuales que aún no están en GHL
            $table->string('contact_id')->nullable()->after('id')->index();

            // 2. Agregar índices a email y teléfono para optimizar las búsquedas
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('org_customers', function (Blueprint $table) {
            // Revertir los cambios en caso de rollback
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            
            $table->dropColumn('contact_id');
        });
    }
};