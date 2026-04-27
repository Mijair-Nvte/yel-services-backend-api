<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgCompanyUserController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📖 1. DIRECTORIO PÚBLICO (Para Chats, Calendarios, Selects)
     * No requiere permisos de Spatie. Solo ser miembro del Workspace.
     */
    public function directory(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        // 🔧 Activamos el contexto de Spatie para la compañía actual
        setPermissionsTeamId($company->id);

        $directory = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->with(['user:id,name,email', 'user.profile:user_id,avatar', 'user.areaAssignments.area:id,name',
                'user.areaAssignments.position:id,name'])
            ->get()
            ->map(function ($member) use ($company) {
                // 🔍 Extraemos el rol vía Spatie, o validamos si es el dueño
                $roleName = cloneRoleLogic($member, $company);

                return [
                    'id' => $member->user->id,
                    'company_user_id' => $member->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'avatar_url' => optional($member->user->profile)->avatar_url,
                    'role' => $roleName,
                    'area_assignments' => $member->user->areaAssignments,
                ];
            });

        return response()->json($directory);
    }

    /**
     * ⚙️ 2. LISTADO ADMINISTRATIVO (Para el panel de Settings)
     * Requiere permiso 'view_users'
     */
    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('view_users');

        // 🔧 Activamos el contexto de Spatie
        setPermissionsTeamId($company->id);

        $team = OrgCompanyUser::where('org_company_id', $company->id)
            ->with([
                'user:id,name,email',
                'user.profile:user_id,avatar',
                'user.areaAssignments.area:id,name',
                'user.areaAssignments.position:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json(
            $team->map(function ($member) use ($company) {
                // 🔍 Extraemos el rol vía Spatie
                $roleName = cloneRoleLogic($member, $company);

                return [
                    'id' => $member->id,
                    'role' => $roleName,
                    'user' => [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'avatar_url' => optional($member->user->profile)->avatar_url,
                        'area_assignments' => $member->user->areaAssignments,
                    ],
                ];
            })
        );
    }

    /**
     * 👁️ 3. VER DETALLE DE PERMISOS (Spatie)
     */
    public function show(string $uid, $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('view_users');

        // 🔧 Activamos el contexto de Spatie
        setPermissionsTeamId($company->id);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->with(['user.profile'])
            ->firstOrFail();

        $roleName = cloneRoleLogic($member, $company);

        return response()->json([
            'member_info' => $member,
            'spatie_data' => [
                'role' => $roleName,
                'active_permissions' => $member->user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * ✏️ 4. ACTUALIZAR ROL Y PERMISOS
     */
    public function update(Request $request, string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('manage_users');

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->with('user')
            ->firstOrFail();

        $data = $request->validate([
            'role' => 'sometimes|string|in:admin,member',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
        ]);

        // 🔥 CORRECCIÓN: Si te envían 'is_active', eso sí va a la DB de la compañía.
        if (isset($data['is_active'])) {
            $member->update(['is_active' => $data['is_active']]);
        }

        // 🔥 CORRECCIÓN: Quitamos el $member->update(['role' => ...]) porque la columna ya no existe.
        // Todo el manejo de roles (admin/member) pasa a ser exclusivo de Spatie.

        if ($member->user->id !== $company->owner_id) {
            setPermissionsTeamId($company->id);

            // 1. Asignamos admin o member vía Spatie
            if (isset($data['role'])) {
                $member->user->syncRoles([$data['role']]);
            }

            // 2. Asignamos los permisos específicos
            if (isset($data['permissions'])) {
                $member->user->syncPermissions($data['permissions']);
            }
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Accesos y permisos actualizados correctamente',
            'member' => $member,
        ]);
    }

    /**
     * ❌ 5. ELIMINAR USUARIO
     */
    public function destroy(string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('manage_users');

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $member->delete();

        return response()->json(['message' => 'Usuario eliminado de la compañía']);
    }
}

/**
 * 💡 Helper local (solo para no repetir código)
 * Evalúa si es Owner, y si no, le pregunta a Spatie su rol.
 */
function cloneRoleLogic($member, $company)
{
    if ($member->user->id === $company->owner_id) {
        return 'owner';
    }

    // Retorna el primer rol de Spatie (ej: 'admin' o 'member'), por defecto 'member'
    return $member->user->getRoleNames()->first() ?? 'member';
}
