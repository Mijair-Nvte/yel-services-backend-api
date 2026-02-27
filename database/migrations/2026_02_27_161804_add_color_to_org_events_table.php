<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('org_events', function (Blueprint $table) {
            // Agregamos la columna 'color', por defecto será 'blue'
            $table->string('color', 50)->default('blue')->after('description');
        });
    }

    public function down()
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
