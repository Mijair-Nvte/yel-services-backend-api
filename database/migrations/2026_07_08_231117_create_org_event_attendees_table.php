<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_event_attendees', function (Blueprint $table) {
            $table->id();
            
            // Relación con el evento
            $table->foreignId('org_event_id')->constrained('org_events')->onDelete('cascade');
            
            // Relación con el usuario
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();

            // 🔥 Regla importante: Evitamos que un usuario confirme asistencia más de una vez al mismo evento
            $table->unique(['org_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_event_attendees');
    }
};