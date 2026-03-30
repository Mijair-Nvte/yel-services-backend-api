<?php

namespace App\Events;

use App\Models\OrgCompanyNotice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoticeCreated
{
    use Dispatchable, SerializesModels;

    public OrgCompanyNotice $notice;

    public function __construct(OrgCompanyNotice $notice)
    {
        $this->notice = $notice;
    }
}
