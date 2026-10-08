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
        Schema::create('org_courses', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('org_company_id')->constrained('org_companies')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            
            // Bucket Público (Cloudflare R2)
            $table->string('cover_image_url')->nullable(); 
            $table->string('preview_video_url')->nullable(); 
            
            // Monetización / RevenueCat
            $table->boolean('is_free')->default(true);
            $table->string('revenuecat_entitlement_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            
            // Estado y metadatos extras
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('metadata')->nullable(); // Para campos extra a futuro
            
            $table->timestamps();
            $table->softDeletes();
            
            // Un slug debe ser único por cada compañía
            $table->unique(['org_company_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_courses');
    }
};