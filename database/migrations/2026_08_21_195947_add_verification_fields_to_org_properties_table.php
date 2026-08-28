<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            // Agregamos el estado de verificación
            $table->enum('verification_status', ['not_applicable', 'pending', 'verified', 'rejected'])
                ->default('not_applicable')
                ->after('closing_type');

            // Campos para auditoría
            $table->timestamp('verified_at')->nullable()->after('verification_status');

            // Relación con el usuario que verificó (suponiendo que usas la tabla 'users')
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('org_properties', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verification_status', 'verified_at', 'verified_by']);
        });
    }
};
