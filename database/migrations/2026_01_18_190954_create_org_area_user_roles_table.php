<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_area_user_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('org_area_id')
                ->constrained('org_areas')
                ->cascadeOnDelete();

            $table->foreignId('org_role_id')
                ->constrained('org_roles')
                ->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['user_id', 'org_area_id', 'org_role_id'],
                'org_area_user_role_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_area_user_roles');
    }
};
