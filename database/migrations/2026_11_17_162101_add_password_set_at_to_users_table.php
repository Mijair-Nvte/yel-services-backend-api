<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orígenes de inscripción que corresponden a importaciones masivas.
     * En producción no hay ninguno todavía; en local marca los de prueba.
     */
    private array $importSources = ['Importación CSV', 'kajabi_import'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
            $table->string('imported_from', 50)->nullable()->after('password_set_at');
        });

        // Usuarios que llegaron por importación → pendientes de crear contraseña
        $importedUserIds = DB::table('org_course_enrollments')
            ->where(function ($q) {
                $q->whereIn('source', $this->importSources)
                    ->orWhere('source', 'like', '%Kajabi%');
            })
            ->pluck('user_id')
            ->unique();

        if ($importedUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $importedUserIds)
                ->update(['imported_from' => 'kajabi']);
        }

        // Todos los demás ya crearon su contraseña ellos mismos
        DB::table('users')
            ->whereNull('password_set_at')
            ->whereNotIn('id', $importedUserIds)
            ->update(['password_set_at' => DB::raw('COALESCE(email_verified_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_set_at', 'imported_from']);
        });
    }
};