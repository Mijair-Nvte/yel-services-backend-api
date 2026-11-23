<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('org_projects', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // Para mantener el estándar de uids que ya usas
            $table->foreignId('org_company_id')->constrained('org_companies')->onDelete('cascade');
            
            $table->string('name'); // Ej. "Planificación Noviembre 2026"
            $table->text('description')->nullable();
            
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])->default('draft');
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('org_projects');
    }
};