<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('org_company_links', function (Blueprint $table) {
            $table->id();

            $table->string('uid')->unique();

            // Relación con compañía
            $table->foreignId('org_company_id')
                ->constrained('org_companies')
                ->cascadeOnDelete();

            // Campos del link
            $table->string('title');
            $table->string('url');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_company_links');
    }
};
