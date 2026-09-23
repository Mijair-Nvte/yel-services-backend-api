<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('org_event_id')->constrained('org_events')->cascadeOnDelete();
            $table->foreignId('org_customer_id')->constrained('org_customers')->cascadeOnDelete();
            
            $table->timestamp('registered_at')->nullable();
            $table->boolean('is_new_lead')->default(true);
            $table->boolean('attended')->default(false);
            $table->integer('ticket_quantity')->default(1);
            $table->string('source')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            // Evitar registros duplicados del mismo cliente en el mismo evento
            $table->unique(['org_event_id', 'org_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_event_registrations');
    }
};