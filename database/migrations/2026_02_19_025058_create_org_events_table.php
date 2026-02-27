<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_events', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();

            // Multi-tenant
            $table->foreignId('org_company_id')
                  ->constrained('org_companies')
                  ->cascadeOnDelete();

            // Usuario creador
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Datos del evento
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();

            // Tiempo
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_all_day')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Índices útiles para calendario
            $table->index(['org_company_id', 'starts_at']);
            $table->index(['org_company_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_events');
    }
};

