<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();

            $table->string('name');
            $table->foreignId('parent_id')->nullable()
                ->constrained('folders')
                ->cascadeOnDelete();

            // 🔥 POLIMÓRFICO
            $table->unsignedBigInteger('folderable_id');
            $table->string('folderable_type');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['folderable_id', 'folderable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
