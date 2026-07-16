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
        Schema::table('org_loan_applications', function (Blueprint $table) {

            $table->date('applicant_dob')
                ->nullable()
                ->change();

            $table->string('applicant_address', 255)
                ->nullable()
                ->change();



            $table->string('loan_type', 255)
                ->nullable()
                ->change();

            $table->decimal('estimated_amount', 12, 2)
                ->nullable()
                ->default(null)
                ->change();

           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_loan_applications', function (Blueprint $table) {

            $table->date('applicant_dob')
                ->nullable(false)
                ->change();

            $table->string('applicant_address', 255)
                ->nullable(false)
                ->change();


            $table->string('loan_type', 255)
                ->nullable(false)
                ->change();

            $table->decimal('estimated_amount', 12, 2)
                ->nullable(false)
                ->default(0)
                ->change();

          
        });
    }
};