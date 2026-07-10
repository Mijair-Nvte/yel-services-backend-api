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
        Schema::create('org_service_default_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_service_id')->constrained('org_services')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Índice compuesto único para evitar que un mismo usuario siga el mismo servicio por defecto dos veces
            $table->unique(['org_service_id', 'user_id'], 'service_default_follower_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_service_default_followers');
    }
};
