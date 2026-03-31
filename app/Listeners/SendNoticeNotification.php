<?php

namespace App\Listeners;

use App\Events\NoticeCreated;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class SendNoticeNotification
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(NoticeCreated $event): void
    {
        $notice = $event->notice;

        $orgCompanyId = $notice->org_company_id;

        // 🔥 Agregamos el filtro para excluir al creador
        $users = User::whereHas('companies', function ($q) use ($orgCompanyId) {
            $q->where('org_company_id', $orgCompanyId);
        })
            ->where('id', '!=', auth()->id()) // <-- ESTA LÍNEA ES LA CLAVE
            ->get();

        // Si solo estaba el creador, no enviamos nada
        if ($users->isEmpty()) {
            return;
        }

        $this->notificationService->send(
            $users,
            'notice.created',
            [
                'title' => $notice->title,
                'notice_id' => $notice->id,
            ],
            $orgCompanyId
        );
    }
}
