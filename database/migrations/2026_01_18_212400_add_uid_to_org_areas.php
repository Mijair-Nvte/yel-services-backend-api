<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_areas', function (Blueprint $table) {
            $table
                ->string('uid')
                ->unique()
                ->after('id');
        });

        // Backfill para registros existentes
        DB::table('org_areas')->get()->each(function ($area) {
            DB::table('org_areas')
                ->where('id', $area->id)
                ->update([
                    'uid' => 'dep_'.Str::ulid(),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('org_areas', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
