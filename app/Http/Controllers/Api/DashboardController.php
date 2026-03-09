<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Document;
use App\Models\Folder;
use App\Models\OrgCompany;
use App\Models\OrgCompanyNotice;
use App\Models\OrgCompanyUser;
use App\Models\OrgEvent;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use AuthorizesWorkspace;

    public function overview(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        $this->authorizeWorkspace($company);

        $now = Carbon::now();

        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $totalUsers = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        */

        $totalEvents = OrgEvent::where('org_company_id', $company->id)->count();

        $eventsThisMonth = OrgEvent::where('org_company_id', $company->id)
            ->whereMonth('starts_at', $now->month)
            ->whereYear('starts_at', $now->year)
            ->count();

        $upcomingEvents = OrgEvent::where('org_company_id', $company->id)
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->limit(5)
            ->get([
                'uid',
                'title',
                'starts_at',
                'ends_at',
                'color',
                'location'
            ]);

        /*
        |--------------------------------------------------------------------------
        | DOCUMENTS
        |--------------------------------------------------------------------------
        */

        $totalDocuments = Document::count();

        $documentsThisMonth = Document::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $recentDocuments = Document::latest()
            ->limit(5)
            ->get([
                'uid',
                'title',
                'file_name',
                'created_at'
            ]);

        /*
        |--------------------------------------------------------------------------
        | FOLDERS
        |--------------------------------------------------------------------------
        */

        $totalFolders = Folder::count();

        /*
        |--------------------------------------------------------------------------
        | NOTICES
        |--------------------------------------------------------------------------
        */

        $totalNotices = OrgCompanyNotice::where('org_company_id', $company->id)
            ->count();

        $activeNotices = OrgCompanyNotice::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->count();

        $pinnedNotices = OrgCompanyNotice::where('org_company_id', $company->id)
            ->where('is_pinned', true)
            ->count();

        $recentNotices = OrgCompanyNotice::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'uid',
                'title',
                'notice_level_id',
                'created_at'
            ]);

        $pinnedNoticeList = OrgCompanyNotice::where('org_company_id', $company->id)
            ->where('is_pinned', true)
            ->orderByDesc('pinned_until')
            ->limit(3)
            ->get([
                'uid',
                'title',
                'pinned_until'
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'stats' => [

                'users' => $totalUsers,

                'events_total' => $totalEvents,
                'events_this_month' => $eventsThisMonth,

                'documents_total' => $totalDocuments,
                'documents_this_month' => $documentsThisMonth,

                'folders_total' => $totalFolders,

                'notices_total' => $totalNotices,
                'notices_active' => $activeNotices,
                'notices_pinned' => $pinnedNotices,
            ],

            'upcoming_events' => $upcomingEvents,

            'recent_documents' => $recentDocuments,

            'recent_notices' => $recentNotices,

            'pinned_notices' => $pinnedNoticeList,
        ]);
    }
}