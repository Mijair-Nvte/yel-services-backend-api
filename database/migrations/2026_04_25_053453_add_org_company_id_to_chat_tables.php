<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregamos la columna a chat_participants
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('org_company_id')->nullable()->after('id');
            $table->foreign('org_company_id')->references('id')->on('org_companies')->onDelete('cascade');
        });

        // 2. Agregamos la columna a chat_messages
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('org_company_id')->nullable()->after('id');
            $table->foreign('org_company_id')->references('id')->on('org_companies')->onDelete('cascade');
        });

        // 3. 💡 TRUCO: Llenamos los datos existentes basados en la conversación
        // Esto evita que la migración falle si ya tienes chats creados.
        DB::statement('
            UPDATE chat_participants p 
            JOIN chat_conversations c ON p.chat_conversation_id = c.id 
            SET p.org_company_id = c.org_company_id
        ');

        DB::statement('
            UPDATE chat_messages m 
            JOIN chat_conversations c ON m.chat_conversation_id = c.id 
            SET m.org_company_id = c.org_company_id
        ');

        // 4. Ahora que están llenos, los hacemos obligatorios (NOT NULL)
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('org_company_id')->nullable(false)->change();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('org_company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['org_company_id']);
            $table->dropColumn('org_company_id');
        });

        Schema::table('chat_participants', function (Blueprint $table) {
            $table->dropForeign(['org_company_id']);
            $table->dropColumn('org_company_id');
        });
    }
};
