<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $userId,
        public string $type,
        public array $data,
        public ?int $orgCompanyId = null
    ) {}

    public function handle(): void
    {
        $notification = Notification::create([
            'user_id' => $this->userId,
            'org_company_id' => $this->orgCompanyId,
            'type' => $this->type,
            'data' => $this->data,
        ]);

        // 🔥 Realtime
      event(new NotificationCreated($notification));
    }
}
