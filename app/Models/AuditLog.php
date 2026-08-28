<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class AuditLog extends SpatieActivity
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            
            // 1. Generar UID
            if (empty($model->uid)) {
                $model->uid = 'log_' . Str::random(15);
            }
            
            // 2. Capturar Red
            if (!app()->runningInConsole()) {
                $model->ip_address = request()->ip();
                $model->user_agent = request()->userAgent();
            }

            // 3. Capturar Tenant (org_company_id) Súper Mejorado
            if (empty($model->org_company_id)) {
                // Prioridad A: Intentar sacarlo del registro afectado (Ej: Tu Loan Application)
                if ($model->subject && isset($model->subject->org_company_id)) {
                    $model->org_company_id = $model->subject->org_company_id;
                }
                // Prioridad B: Si el registro no tiene empresa, sacarlo del usuario que hizo la acción
                elseif ($model->causer && isset($model->causer->org_company_id)) {
                    $model->org_company_id = $model->causer->org_company_id;
                }
                // Prioridad C: Fallback clásico de sesión web
                elseif (auth()->check() && isset(auth()->user()->org_company_id)) {
                    $model->org_company_id = auth()->user()->org_company_id;
                }
            }
        });
    }
}