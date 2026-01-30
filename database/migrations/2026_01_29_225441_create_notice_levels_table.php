<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_levels', function (Blueprint $table) {
            $table->id();

            $table->string('name'); // Informativo
            $table->string('slug')->unique(); // info
            $table->string('color')->nullable(); // #2563EB

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_levels');
    }
};
