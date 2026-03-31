<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\EventCreated;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class SendEventNotification
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(EventCreated $event): void
    {
        $eventModel = $event->event;
        $orgCompanyId = $eventModel->org_company_id;

        
        $users = User::whereHas('companies', function ($q) use ($orgCompanyId) {
            $q->where('org_company_id', $orgCompanyId);
        })
        ->where('id', '!=', auth()->id()) // <-- ESTA LÍNEA ES LA CLAVE
        ->get();

        // Si solo estaba el creador en la compañía, no enviamos nada
        if ($users->isEmpty()) {
            return;
        }

        $this->notificationService->send(
            $users,
            'event.created',
            [
                'title' => $eventModel->title,
                'event_id' => $eventModel->id,
                'starts_at' => $eventModel->starts_at,
            ],
            $orgCompanyId
        );
    }
}