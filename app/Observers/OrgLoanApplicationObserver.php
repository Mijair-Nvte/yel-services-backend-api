<?php

namespace App\Observers;

use App\Models\OrgLoanApplication;
use App\Traits\TriggersModuleAutomations;
use App\Mail\LoanStatusUpdatedMail;
class OrgLoanApplicationObserver
{
    use TriggersModuleAutomations;

    /**
     * Handle the OrgLoanApplication "created" event.
     */
    public function created(OrgLoanApplication $loan)
    {
        // Aseguramos que las relaciones estén cargadas
        $loan->load(['customer', 'user']);

        // Dispara la automatización hacia GHL al crear
        if ($loan->company) {
            $this->triggerAutomations($loan->company, 'loans', 'created', $loan);
        }
    }

    /**
     * Handle the OrgLoanApplication "updated" event.
     */
    public function updated(OrgLoanApplication $loan)
    {
        // Verificamos si el estatus realmente cambió para evitar llamadas innecesarias
        if ($loan->isDirty('status')) {
            $loan->load(['customer', 'user']);

            // Dispara la automatización hacia GHL (o servicios de correo) al actualizar
            if ($loan->company) {
                $this->triggerAutomations($loan->company, 'loans', 'updated', $loan);
            }
        }
    }
}