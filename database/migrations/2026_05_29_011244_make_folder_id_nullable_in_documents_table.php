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
        Schema::table('documents', function (Blueprint $table) {
            // Modificamos la columna para que acepte valores nulos
            $table->bigInteger('folder_id')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // En caso de rollback, vuelve a ser obligatoria
            // Nota: Asegúrate de que no haya registros con NULL antes de revertir
            $table->bigInteger('folder_id')->unsigned()->nullable(false)->change();
        });
    }
};