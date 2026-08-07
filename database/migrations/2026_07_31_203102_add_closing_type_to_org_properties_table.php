<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            $table->enum('closing_type', ['yel_internal', 'external'])
                  ->default('external')
                  ->after('portfolio_type')
                  ->comment('yel_internal contabiliza para el sistema de niveles y descuentos de YEL. external es solo para gestión de portafolio del usuario.');
        });
    }

    public function down(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            $table->dropColumn('closing_type');
        });
    }
};