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
        Schema::table('org_services', function (Blueprint $table) {
            // Agregamos la columna para la imagen, permitiendo nulos por si algún servicio no tiene portada
            $table->string('cover_image')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_services', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};