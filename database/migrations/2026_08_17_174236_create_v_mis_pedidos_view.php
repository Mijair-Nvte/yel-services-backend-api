<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usamos CREATE OR REPLACE por si necesitas modificarla en el futuro
        DB::statement("
            CREATE OR REPLACE VIEW v_mis_pedidos AS
            SELECT 
                s.id AS sale_id,
                s.uid AS sale_uid,
                s.total_amount,
                s.payment_status,
                s.created_at AS purchase_date,
                
                c.user_id,
                c.id AS org_customer_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                
                COALESCE(srv.name, s.product_name) AS item_name,
                
                so.uid AS order_uid,
                so.status AS order_status
                
            FROM org_sales s
            INNER JOIN org_customers c ON s.org_customer_id = c.id
            LEFT JOIN org_services srv ON s.org_service_id = srv.id
            LEFT JOIN org_service_orders so ON so.org_sale_id = s.id;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_mis_pedidos;");
    }
};