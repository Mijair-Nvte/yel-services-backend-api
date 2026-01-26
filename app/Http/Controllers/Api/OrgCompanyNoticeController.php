<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
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
                ->where('is_active', true)
                ->with([
                    'creator.profile', // 👈 CLAVE
                ])
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
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
            'level' => 'in:info,warning,urgent',
            'published_at' => 'nullable|date',
        ]);

        $notice = OrgCompanyNotice::create([
            'uid' => 'ntc_'.Str::ulid(),
            'org_company_id' => $company->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'level' => $data['level'] ?? 'info',
            'published_at' => $data['published_at'] ?? now(),
            'is_active' => true,
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
            'level' => 'in:info,warning,urgent',
            'published_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $notice->update($data);

        return response()->json($notice);
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
}
