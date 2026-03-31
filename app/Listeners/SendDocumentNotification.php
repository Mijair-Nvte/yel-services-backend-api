<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DocumentUploaded;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class SendDocumentNotification
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(DocumentUploaded $event): void
    {
        $document = $event->document;
        $orgCompanyId = $document->folder->folderable_id;

        // 🔥 Agregamos la exclusión del creador aquí también
        $users = User::whereHas('companies', function ($q) use ($orgCompanyId) {
            $q->where('org_company_id', $orgCompanyId);
        })
            ->where('id', '!=', auth()->id()) // <-- CLAVE para no auto-notificarte
            ->get();

        if ($users->isEmpty()) {
            return; // No enviamos nada si no hay otros usuarios
        }

        $this->notificationService->send(
            $users,
             'document.created',
            [
                'title' => $document->title,
                'document_id' => $document->id,
            ],
            $orgCompanyId
        );
    }
}
