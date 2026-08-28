<?php

namespace App\Traits;

use App\Jobs\ProcessModuleAutomations;
use App\Models\OrgCompany;
use Illuminate\Database\Eloquent\Model;

trait TriggersModuleAutomations
{
    /**
     * Dispara de manera asíncrona las configuraciones de un módulo (Webhooks, Notificaciones, Pusher, etc).
     *
     * @param OrgCompany $company    La compañía dueña de la acción.
     * @param string     $moduleName El nombre del módulo (ej: 'loans', 'insurances', 'sales').
     * @param string     $eventName  El evento que ocurrió (ej: 'created', 'updated', 'deleted', 'approved').
     * @param Model      $entity     El modelo afectado (la solicitud de préstamo, la póliza, etc).
     */
    protected function triggerAutomations(OrgCompany $company, string $moduleName, string $eventName, Model $entity)
    {
        // Aquí mandamos el trabajo a la cola (background).
        // El controlador NO esperará a que esto termine, responderá inmediatamente al frontend.
        ProcessModuleAutomations::dispatch($company, $moduleName, $eventName, $entity);
    }
}