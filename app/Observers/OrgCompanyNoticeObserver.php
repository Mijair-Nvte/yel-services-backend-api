<?php

namespace App\Observers;

use App\Events\NoticeCreated;
use App\Models\OrgCompanyNotice;

class OrgCompanyNoticeObserver
{
    public function created(OrgCompanyNotice $notice): void
    {
        event(new NoticeCreated($notice));
    }
}
