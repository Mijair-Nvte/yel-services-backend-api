<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('org_event_registrations', function (Blueprint $table) {
            // Agregamos el status después de 'attended' para mantener orden lógico
            $table->string('contact_status', 50)->default('pending')->after('attended');
        });
    }

    public function down()
    {
        Schema::table('org_event_registrations', function (Blueprint $table) {
            $table->dropColumn('contact_status');
        });
    }
};
