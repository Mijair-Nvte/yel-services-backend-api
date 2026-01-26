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
        Schema::create('org_company_notices', function (Blueprint $table) {
            $table->id();

            $table->string('uid')->unique();

            $table->foreignId('org_company_id')
                ->constrained('org_companies')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('body');

            $table->enum('level', ['info', 'warning', 'urgent'])
                ->default('info');

            $table->timestamp('published_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_company_notices');
    }
};
