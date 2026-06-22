<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PASO 1: Agregar la nueva columna de relación (debe ser nullable al inicio)
        Schema::table('org_sales', function (Blueprint $table) {
            $table->foreignId('org_customer_id')
                  ->nullable()
                  ->after('source_id')
                  ->constrained('org_customers')
                  ->nullOnDelete();
        });

        // PASO 2: Migrar los datos existentes de org_sales a org_customers
        // Usamos DB::table para evitar conflictos con los modelos durante la migración
        $sales = DB::table('org_sales')->get();

        foreach ($sales as $sale) {
            // Si la venta no tiene datos mínimos de cliente, la saltamos
            if (empty($sale->customer_email) && empty($sale->customer_name)) {
                continue;
            }

            // Separar el nombre completo en Primer Nombre y Apellido de forma simple
            $nameParts = explode(' ', trim($sale->customer_name), 2);
            $firstName = $nameParts[0] ?? 'Cliente';
            $lastName = $nameParts[1] ?? null;

            $customerId = null;

            // Evitar duplicados: Si el cliente ya fue creado por otra venta previa del mismo email
            if (!empty($sale->customer_email)) {
                $existingCustomer = DB::table('org_customers')
                    ->where('org_company_id', $sale->org_company_id)
                    ->where('email', $sale->customer_email)
                    ->first();

                if ($existingCustomer) {
                    $customerId = $existingCustomer->id;
                }
            }

            // Si el cliente no existe en nuestro directorio, lo registramos
            if (!$customerId) {
                $customerId = DB::table('org_customers')->insertGetId([
                    'uid' => 'cus_' . strtoupper(Str::random(26)),
                    'org_company_id' => $sale->org_company_id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $sale->customer_email,
                    'phone' => $sale->customer_phone,
                    'created_at' => $sale->created_at ?? now(),
                    'updated_at' => $sale->updated_at ?? now(),
                ]);
            }

            // Enlazar la venta con el ID del cliente correspondiente
            DB::table('org_sales')
                ->where('id', $sale->id)
                ->update(['org_customer_id' => $customerId]);
        }

        // PASO 3: Ahora que los datos están seguros en org_customers, eliminamos las columnas antiguas
        Schema::table('org_sales', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_sales', function (Blueprint $table) {
            // Revertir los campos en caso de rollback
            $table->string('customer_name')->nullable()->after('source_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 30)->nullable()->after('customer_email');

            $table->dropForeign(['org_customer_id']);
            $table->dropColumn('org_customer_id');
        });
    }
};