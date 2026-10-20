<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            // Agregamos la columna event_tag opcional y única por seguridad
            $table->string('event_tag')->nullable()->after('slug')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropColumn('event_tag');
        });
    }
};