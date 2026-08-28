<?php

namespace App\Jobs;

use App\Mail\InsuranceRequestMail;
use App\Mail\LoanRequestMail;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
// 📧 Importamos los Mailables de cada módulo
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// use App\Mail\PropertyRequestMail; // Para el futuro

class ProcessModuleAutomations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    public $moduleName;

    public $eventName;

    public $entity;

    /**
     * Create a new job instance.
     */
    public function __construct(OrgCompany $company, string $moduleName, string $eventName, Model $entity)
    {
        $this->company = $company;
        $this->moduleName = $moduleName;
        $this->eventName = $eventName;
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // 1. Buscamos la configuración activa del módulo para esta empresa
        $settingRecord = OrgModuleSetting::where('org_company_id', $this->company->id)
            ->where('module_name', $this->moduleName)
            ->where('is_active', true)
            ->first();

        // 2. Si no hay configuración o está vacía, abortamos el Job silenciosamente.
        if (! $settingRecord || empty($settingRecord->settings)) {
            return;
        }

        $settings = $settingRecord->settings;

        // 3. 🚀 Orquestador de Tareas
        $this->processWebhooks($settings);
        $this->processInternalNotifications($settings);

        // $this->processPusherRealTime($settings); // <- Listo para el futuro
    }

    /**
     * 🌐 Enviar Webhook a sistemas externos (Ej: GoHighLevel, Zapier, Make)
     */
    protected function processWebhooks(array $settings)
    {
        $integrations = $settings['integrations'] ?? [];

        if (isset($integrations['webhook_active']) && $integrations['webhook_active'] === true) {
            $url = $integrations['gohighlevel_webhook_url'] ?? null;

            if ($url) {
                try {
                    // Usamos Http con un timeout de 10 segundos
                    Http::timeout(10)->post($url, [
                        'event' => $this->eventName,
                        'module' => $this->moduleName,
                        'company_uid' => $this->company->uid,
                        'payload' => $this->entity->toArray(), // Mandamos toda la info del registro
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } catch (\Exception $e) {
                    Log::error("Fallo al enviar Webhook de {$this->moduleName}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * 🔔 Enviar Notificaciones Internas al equipo (Email)
     */
    protected function processInternalNotifications(array $settings)
    {
        $notifications = $settings['notifications'] ?? [];
        $shouldNotify = false;

        // 1. Determinar dinámicamente si se debe notificar según el módulo y el evento
        if ($this->eventName === 'created') {
            if ($this->moduleName === 'loans') {
                $shouldNotify = $notifications['notify_on_new_loan'] ?? false;
            } elseif ($this->moduleName === 'insurances') {
                $shouldNotify = $notifications['notify_on_new_insurance'] ?? false;
            } else {
                // Fallback genérico para cuando agregues ventas o propiedades
                $shouldNotify = $notifications['notify_on_create'] ?? false;
            }
        }

        // 2. Si la configuración dice que sí, procedemos a enviar los correos
        if ($shouldNotify) {
            $userIds = $notifications['assigned_users_to_notify'] ?? [];

            if (! empty($userIds)) {
                // Obtenemos a todos los usuarios seleccionados en la configuración de React
                $users = User::whereIn('id', $userIds)->get();

                foreach ($users as $user) {
                    try {
                        // 3. Evaluamos dinámicamente qué Mailable enviar
                        if ($this->moduleName === 'loans') {
                            Mail::to($user->email)->send(new LoanRequestMail($this->entity, $user, $this->company));
                        } elseif ($this->moduleName === 'insurances') {
                            Mail::to($user->email)->send(new InsuranceRequestMail($this->entity, $user, $this->company));
                        }

                        Log::info("Notificación de email enviada a: {$user->email} | Módulo: {$this->moduleName}");

                    } catch (\Exception $e) {
                        // Aislamos el error para que un fallo en un email no detenga el envío al resto del equipo
                        Log::error("Error enviando email a {$user->email} para módulo {$this->moduleName}: ".$e->getMessage());
                    }
                }
            }
        }
    }
}
