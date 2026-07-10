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
            // Añadimos el encargado por defecto después del precio
            $table->foreignId('default_assignee_id')
                  ->nullable()
                  ->after('price')
                  ->constrained('users')
                  ->onDelete('set null'); // Si el usuario se elimina, el servicio queda sin encargado por defecto pero no se borra
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_services', function (Blueprint $table) {
            $table->dropForeign(['default_assignee_id']);
            $table->dropColumn('default_assignee_id');
        });
    }
};