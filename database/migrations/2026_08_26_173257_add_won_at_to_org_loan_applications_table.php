<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_loan_applications', function (Blueprint $table) {
            // Agregamos won_at justo después de status para mantener el orden lógico
            $table->timestamp('won_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('org_loan_applications', function (Blueprint $table) {
            $table->dropColumn('won_at');
        });
    }
};