<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('org_project_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->onDelete('cascade');
            $table->foreignId('org_project_id')->constrained('org_projects')->onDelete('cascade');
            
            $table->string('name'); // Título de la tarea
            $table->text('description')->nullable();
            
            // Clasificación Principal
            $table->string('department'); // Ej: marketing_redes, eventos, ventas, pauta
            $table->string('platform')->nullable(); // Ej: facebook, instagram, tiktok, zoom, in_person
            $table->string('task_type')->nullable(); // Ej: reel, carrusel, story, webinar, closing_goal
            
            // Tiempos
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            
            // Estatus Kanban
            $table->enum('status', ['pending', 'in_progress', 'review', 'completed'])->default('pending');
            
            // Responsables y Relaciones (Acorde a tu BD actual)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('org_event_id')->nullable()->constrained('org_events')->onDelete('set null');
            
            // EL CAMPO MAGICO PARA EL FRONTEND (Guiones, Presupuestos, Links, Pilares)
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('org_project_tasks');
    }
};