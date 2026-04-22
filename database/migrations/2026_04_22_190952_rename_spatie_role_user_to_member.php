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
       \DB::table('roles')
        ->where('name', 'user')
        ->update(['name' => 'member']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       \DB::table('roles')
        ->where('name', 'member')
        ->update(['name' => 'user']);
    }
};
