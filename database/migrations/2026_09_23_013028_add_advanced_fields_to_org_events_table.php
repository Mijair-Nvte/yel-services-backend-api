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
        Schema::table('org_events', function (Blueprint $table) {
            // Slug único amigable para SEO
            $table->string('slug')->nullable()->after('title');
            
            // Imágenes separadas: cover (miniatura/tarjeta) y banner (portada grande de detalles)
            $table->string('cover_image')->nullable()->after('description');
            $table->string('banner_image')->nullable()->after('cover_image');
            
            // Recursos futuros (ej. PDFs de guía, links de descarga en formato JSON)
            $table->json('resources')->nullable()->after('banner_image');
            
            // Metadatos flexibles para cualquier dato extra sin alterar columnas
            $table->json('meta')->nullable()->after('resources');

            // Aseguramos que el slug sea único por cada compañía para evitar colisiones
            $table->unique(['org_company_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_events', function (Blueprint $table) {
            $table->dropUnique(['org_company_id', 'slug']);
            $table->dropColumn(['slug', 'cover_image', 'banner_image', 'resources', 'meta']);
        });
    }
};