<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            // Agregamos la columna 'target_platform'. 
            // Ponemos 'yel_services' por defecto para no afectar los eventos que ya tienes creados en tu base de datos.
            $table->string('target_platform')->default('yel_services')->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropColumn('target_platform');
        });
    }
};