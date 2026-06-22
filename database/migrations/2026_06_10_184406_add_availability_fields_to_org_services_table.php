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
            // Usamos string para evitar problemas a futuro si agregas más tipos de disponibilidad
            $table->string('availability_type')->default('all')->after('description')->comment('all, restricted');
            $table->json('available_states')->nullable()->after('availability_type')->comment('Ej: ["TX", "FL"]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_services', function (Blueprint $table) {
            $table->dropColumn(['availability_type', 'available_states']);
        });
    }
};