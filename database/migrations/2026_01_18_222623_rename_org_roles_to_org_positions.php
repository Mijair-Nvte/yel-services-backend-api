<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
    {
        Schema::rename('org_roles', 'org_positions');
    }

    public function down(): void
    {
        Schema::rename('org_positions', 'org_roles');
    }
};
