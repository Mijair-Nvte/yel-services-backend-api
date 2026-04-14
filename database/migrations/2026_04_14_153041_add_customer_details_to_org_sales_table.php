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
        Schema::table('org_sales', function (Blueprint $table) {
            // customer_email ya existe, así que agregamos los nuevos justo después
            $table->string('customer_phone', 30)->nullable()->after('customer_email');
            $table->string('customer_origin')->nullable()->after('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            $table->dropColumn(['customer_phone', 'customer_origin']);
        });
    }
};
