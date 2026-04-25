<?php

namespace App\Http\Controllers\Api;

use App\Events\OrgCompanyNoticeCreated;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgArea;
use App\Models\OrgCompany;
use App\Models\OrgCompanyNotice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgCompanyNoticeController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar avisos globales de una compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_notices');

            $notices = OrgCompanyNotice::where('org_company_id', $company->id)
                ->global()
                ->with(['creator.profile', 'level'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->get();

            return response()->json($notices, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Compañía no encontrada.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener avisos.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🏢 Listar los avisos por áreas
     */
    public function indexArea(string $uid, string $areaUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_notices');

            $area = OrgArea::where('uid', $areaUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $notices = OrgCompanyNotice::where('org_company_id', $company->id)
                ->forArea($area->id)
                ->with(['creator.profile', 'level'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->get();

            return response()->json($notices, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Área o Compañía no encontrada.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener avisos del área.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 📝 Crear aviso
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_notices');

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
                $area = OrgArea::where('uid', $data['org_area_uid'])
                    ->where('org_company_id', $company->id)
                    ->first();

                if (! $area) {
                    return response()->json(['message' => 'El área especificada no pertenece a esta compañía.'], 422);
                }
                $areaId = $area->id;
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

            event(new OrgCompanyNoticeCreated($notice));

            return response()->json($notice->load('level'), 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el aviso.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔍 Ver detalle de un aviso (Normalizado)
     */
    public function show(string $uid, string $noticeUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_notices');

            $notice = OrgCompanyNotice::where('uid', $noticeUid)
                ->where('org_company_id', $company->id) // 🔥 Validación de pertenencia
                ->with(['company', 'creator.profile', 'level'])
                ->firstOrFail();

            return response()->json($notice, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Aviso no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el aviso.'], 500);
        }
    }

    /**
     * ✏️ Actualizar aviso (Normalizado)
     */
    public function update(Request $request, string $uid, string $noticeUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_notices');

            $notice = OrgCompanyNotice::where('uid', $noticeUid)
                ->where('org_company_id', $company->id) // 🔥 Seguridad extra
                ->firstOrFail();

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

            return response()->json($notice->load('level'), 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el aviso.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar aviso (Normalizado)
     */
    public function destroy(string $uid, string $noticeUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_notices');

            $notice = OrgCompanyNotice::where('uid', $noticeUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $notice->delete();

            return response()->json(['message' => 'Aviso eliminado correctamente'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el aviso.'], 500);
        }
    }

    /**
     * 📌 Fijar un aviso (Pin) (Normalizado)
     */
    public function pin(Request $request, string $uid, string $noticeUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_notices');

            $notice = OrgCompanyNotice::where('uid', $noticeUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $data = $request->validate([
                'days' => 'nullable|integer|min:1|max:30',
            ]);

            $notice->update([
                'is_pinned' => true,
                'pinned_until' => isset($data['days']) ? now()->addDays($data['days']) : null,
            ]);

            return response()->json([
                'message' => 'Aviso fijado correctamente',
                'notice' => $notice->load('level'),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al fijar el aviso.'], 500);
        }
    }

    /**
     * 📍 Desfijar un aviso (Unpin) (Normalizado)
     */
    public function unpin(string $uid, string $noticeUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_notices');

            $notice = OrgCompanyNotice::where('uid', $noticeUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $notice->update([
                'is_pinned' => false,
                'pinned_until' => null,
            ]);

            return response()->json([
                'message' => 'Aviso desfijado correctamente',
                'notice' => $notice->load('level'),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al desfijar el aviso.'], 500);
        }
    }
}
