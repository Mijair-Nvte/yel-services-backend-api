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
            // Agregamos la columna 'price' de tipo decimal, permitiendo nulos por si hay servicios gratuitos o a cotizar
            $table->decimal('price', 10, 2)->nullable()->after('stripe_price_id')->comment('Precio a mostrar al público');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_services', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};