<?php

namespace App\Events;

use App\Models\OrgCompanyNotice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrgCompanyNoticeCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrgCompanyNotice $notice
    ) {}
}
