<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('org_company_links', function (Blueprint $table) {

            // ✅ Preview fields (WhatsApp style)
            $table->string('preview_image')->nullable()->after('description');

            $table->text('preview_description')->nullable()->after('preview_image');
        });
    }

    public function down(): void
    {
        Schema::table('org_company_links', function (Blueprint $table) {
            $table->dropColumn([
                'preview_image',
                'preview_description',
            ]);
        });
    }
};
