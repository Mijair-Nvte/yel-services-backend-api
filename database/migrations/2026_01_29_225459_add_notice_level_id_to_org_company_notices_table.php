<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_company_notices', function (Blueprint $table) {

            // 1. Agregar FK
            $table->foreignId('notice_level_id')
                ->nullable()
                ->after('body')
                ->constrained('notice_levels')
                ->nullOnDelete();

            // 2. Quitar ENUM viejo
            $table->dropColumn('level');
        });
    }

    public function down(): void
    {
        Schema::table('org_company_notices', function (Blueprint $table) {

            $table->enum('level', ['info', 'warning', 'urgent'])
                ->default('info');

            $table->dropForeign(['notice_level_id']);
            $table->dropColumn('notice_level_id');
        });
    }
};
