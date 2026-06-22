<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            // Agregamos el campo state justo después de address
            $table->string('applicant_state')->nullable()->after('applicant_address');
        });
    }

    public function down(): void
    {
        Schema::table('org_insurance_applications', function (Blueprint $table) {
            $table->dropColumn('applicant_state');
        });
    }
};