<?php

namespace App\Traits;

use App\Models\OrgSale;
use App\Models\OrgService;
use App\Models\OrgServiceOrder;
use Illuminate\Support\Facades\Log;

trait HandlesServiceOrders
{
    /**
     * Genera la instancia operativa de la orden heredando los responsables del catálogo
     */
    protected function createServiceOrder(OrgSale $sale, OrgService $service, int $customerId, int $companyId, string $initiatedBy = 'system'): OrgServiceOrder
    {
        // Creamos la orden instanciando los datos actuales de la plantilla
        $order = OrgServiceOrder::create([
            'org_company_id' => $companyId,
            'org_sale_id' => $sale->id,
            'org_service_id' => $service->id,
            'org_customer_id' => $customerId,
            'assigned_to' => $service->default_assignee_id, // Hereda el Owner asignado por defecto
            'status' => 'pending',
            'metadata' => [
                'initiated_by' => $initiatedBy,
                'cloned_at' => now()->toDateTimeString(),
            ],
        ]);

        // Extraemos los IDs de los seguidores del catálogo original
        $defaultFollowerIds = $service->defaultFollowers()->pluck('users.id')->toArray();

        if (! empty($defaultFollowerIds)) {
            // Sincronizamos los seguidores en el nuevo pivote operacional de la orden
            $order->followers()->sync($defaultFollowerIds);
        }

        Log::info("⚙️ Orden de Trabajo generada con éxito: UID {$order->uid} asignada al usuario ID {$service->default_assignee_id}");

        return $order;
    }
}
