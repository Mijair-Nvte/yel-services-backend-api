<?php

namespace App\Listeners;

use App\Events\OrgCompanyNoticeCreated;
use App\Mail\OrgCompanyNoticeMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrgCompanyNoticeEmails implements ShouldQueue
{
    public function handle(OrgCompanyNoticeCreated $event): void
    {
        $notice = $event->notice;

        // 📌 Aviso GLOBAL de compañía
        if (is_null($notice->org_area_id)) {
            $users = User::whereHas('companies', function ($q) use ($notice) {
                $q->where('org_company_id', $notice->org_company_id)
                  ->where('is_active', true);
            })->get();
        }
        // 📌 Aviso por ÁREA
        else {
            $users = User::whereHas('areaAssignments', function ($q) use ($notice) {
                $q->where('org_area_id', $notice->org_area_id)
                  ->where('is_active', true);
            })->get();
        }

        foreach ($users as $user) {
            Mail::to($user->email)
                ->queue(new OrgCompanyNoticeMail($notice));
        }
    }
}
