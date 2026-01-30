<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use App\Models\OrgCompanyNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgCompanyNoticeController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * Listar avisos globales de una compañía
     */
    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        return response()->json(
            OrgCompanyNotice::where('org_company_id', $company->id)
                ->global()
                ->with(['creator.profile', 'level'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->get()
        );
    }

    // listar los avisos por areas
    public function indexArea(string $companyUid, string $areaUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $area = OrgArea::where('uid', $areaUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();

        return response()->json(
            OrgCompanyNotice::where('org_company_id', $company->id)
                ->forArea($area->id)
                ->with(['creator.profile', 'level'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->get()
        );
    }

    /**
     * Crear aviso global para la compañía
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'notice_level_id' => 'required|exists:notice_levels,id',
            'published_at' => 'nullable|date',
            'org_area_uid' => 'nullable|exists:org_areas,uid',
            'is_pinned' => 'boolean',
        ]);

        $areaId = null;

        if (! empty($data['org_area_uid'])) {
            $areaId = OrgArea::where('uid', $data['org_area_uid'])
                ->where('org_company_id', $company->id)
                ->value('id');
        }

        $notice = OrgCompanyNotice::create([
            'uid' => 'ntc_'.Str::ulid(),
            'org_company_id' => $company->id,
            'org_area_id' => $areaId,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'notice_level_id' => $data['notice_level_id'],
            'published_at' => $data['published_at'] ?? now(),
            'is_active' => true,
            'is_pinned' => $data['is_pinned'] ?? false,

        ]);

        return response()->json($notice, 201);
    }

    /**
     * Ver detalle de un aviso
     */
    public function show(string $uid)
    {
        $notice = OrgCompanyNotice::where('uid', $uid)
            ->with(['company', 'creator'])
            ->firstOrFail();

        $this->authorizeWorkspace($notice->company);

        return response()->json($notice);
    }

    /**
     * Actualizar aviso
     */
    public function update(Request $request, string $uid)
    {
        $notice = OrgCompanyNotice::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($notice->company);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'notice_level_id' => 'sometimes|exists:notice_levels,id',
            'published_at' => 'nullable|date',
            'is_active' => 'boolean',

            'is_pinned' => 'boolean',
            'pinned_until' => 'nullable|date',
        ]);

        $notice->update($data);

        return response()->json(
            $notice->load('level')
        );

    }

    /**
     * Eliminar (soft lógico) un aviso
     */
    public function destroy(string $uid)
    {
        $notice = OrgCompanyNotice::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($notice->company);

        $notice->delete();

        return response()->json([
            'message' => 'Notice deleted successfully',
        ]);
    }

    public function pin(Request $request, string $uid)
    {
        $notice = OrgCompanyNotice::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($notice->company);

        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        $notice->update([
            'is_pinned' => true,
            'pinned_until' => isset($data['days'])
                ? now()->addDays($data['days'])
                : null,
        ]);

        return response()->json([
            'message' => 'Notice pinned successfully',
            'notice' => $notice,
        ]);
    }

    public function unpin(string $uid)
    {
        $notice = OrgCompanyNotice::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($notice->company);

        $notice->update([
            'is_pinned' => false,
            'pinned_until' => null,
        ]);

        return response()->json([
            'message' => 'Notice unpinned successfully',
            'notice' => $notice,
        ]);
    }
}
