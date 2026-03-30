<?php

namespace App\Listeners;

use App\Enums\NotificationType;
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

        $users = User::whereHas('companies', function ($q) use ($orgCompanyId) {
            $q->where('org_company_id', $orgCompanyId);
        })->get();

        $this->notificationService->send(
            $users,
            NotificationType::NOTICE_CREATED,
            [
                'title' => $notice->title,
                'notice_id' => $notice->id,
            ],
            $orgCompanyId
        );
    }
}
