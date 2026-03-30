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

        $users = User::whereHas('companies', function ($q) use ($orgCompanyId) {
            $q->where('org_company_id', $orgCompanyId);
        })->get();

        $this->notificationService->send(
            $users,
            NotificationType::DOCUMENT_UPLOADED,
            [
                'title' => $document->title,
                'document_id' => $document->id,
            ],
            $orgCompanyId
        );
    }
}
