<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('org_course_resources', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            
            // Magia polimórfica: resourceable_id y resourceable_type
            $table->morphs('resourceable'); 
            
            $table->string('title'); // Ej: "Guía de Inicio Rápido"
            $table->string('file_name'); // Ej: "guia_inicio.pdf"
            $table->string('file_url'); // La ruta en Cloudflare R2
            $table->string('mime_type')->nullable(); // application/pdf, audio/mpeg, etc.
            $table->bigInteger('file_size')->nullable(); // En bytes
            
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('org_course_resources');
    }
};