<?php

namespace App\Services;

use App\Mail\InsuranceStatusUpdatedMail;
use App\Mail\LoanStatusUpdatedMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GhlStatusSyncService
{
    public static function sync(Model $entity, ?string $ghlStatus, string $moduleName)
    {

        $normalizedStatus = self::mapStatus($ghlStatus);

        if ($normalizedStatus && strtolower($entity->status) !== strtolower($normalizedStatus)) {

            // Actualizamos el estatus

            $entity->updateQuietly([

                'status' => $normalizedStatus,

            ]);

            Log::info("GHL Sync Service [{$moduleName}]: Entidad {$entity->uid} actualizada a estatus {$normalizedStatus}");

            // Aseguramos cargar las relaciones antes de notificar

            $entity->load(['customer', 'company', 'user']);

            // Disparamos las notificaciones

            self::sendNotifications($entity, $moduleName);

        }

    }

    protected static function mapStatus(?string $status): ?string
    {

        return match ($status) {

            'won' => 'Won',

            'lost' => 'Lost',

            'abandoned' => 'Abandon',

            'open' => 'Open',

            default => null,

        };

    }

    protected static function sendNotifications(Model $entity, string $moduleName)
    {
        if ($moduleName === 'loans') {
            // 🚀 Cambiamos customer por user (el Partner)
            if ($entity->user && $entity->user->email && $entity->company) {
                try {
                    Mail::to($entity->user->email)->queue(
                        new LoanStatusUpdatedMail($entity, $entity->company)
                    );

                    Log::info("GHL Sync Service: Correo de loan encolado exitosamente para Partner {$entity->user->email}");
                } catch (\Exception $e) {
                    Log::error('GHL Sync Service Error al encolar correo de loan: '.$e->getMessage());
                }
            }
        } elseif ($moduleName === 'insurances') {
            // 🚀 Cambiamos customer por user (el Partner)
            if ($entity->user && $entity->user->email && $entity->company) {
                try {
                    Mail::to($entity->user->email)->queue(
                        new InsuranceStatusUpdatedMail($entity, $entity->company)
                    );

                    Log::info("GHL Sync Service: Correo de insurance encolado exitosamente para Partner {$entity->user->email}");
                } catch (\Exception $e) {
                    Log::error('GHL Sync Service Error al encolar correo de insurance: '.$e->getMessage());
                }
            }
        }
    }
}
