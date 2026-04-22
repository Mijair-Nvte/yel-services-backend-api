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
        Schema::table('org_companies', function (Blueprint $table) {
        // Se agrega nullable primero por si ya tienes datos, 
        // luego puedes llenarlo y cambiarlo a no nulo si lo deseas.
        $table->foreignId('owner_id')
              ->after('id')
              ->nullable()
              ->constrained('users')
              ->onDelete('cascade');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('org_companies', function (Blueprint $table) {
        $table->dropForeign(['owner_id']);
        $table->dropColumn('owner_id');
    });
    }
};
