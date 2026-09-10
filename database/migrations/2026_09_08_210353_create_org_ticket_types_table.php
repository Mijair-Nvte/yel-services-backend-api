<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            $table->string('name'); // Ej. 'General', 'VIP', 'Early Bird'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Aquí está tu columna JSON para flexibilidad futura
            $table->json('metadata')->nullable(); 
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_ticket_types');
    }
};