<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCompanyUser;
use App\Models\User;
use Illuminate\Http\Request;

class OrgCompanyUserController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * 📋 Listar equipo completo de una compañía
     */
    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

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
            $team->map(function ($member) {
                return [
                    'id' => $member->id,
                    'role' => $member->role,
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
     * ➕ Agregar usuario a la compañía
     * (por email o user_id)
     */
    public function store(Request $request, string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'email' => 'nullable|email|exists:users,email',
            'role' => 'required|string|in:owner,admin,member',
        ]);

        if (! $data['user_id'] && ! $data['email']) {
            return response()->json([
                'message' => 'user_id o email es requerido',
            ], 422);
        }

        $user = isset($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : User::where('email', $data['email'])->firstOrFail();

        // Evitar duplicados
        $exists = OrgCompanyUser::where([
            'user_id' => $user->id,
            'org_company_id' => $company->id,
        ])->exists();

        if ($exists) {
            return response()->json([
                'message' => 'El usuario ya pertenece a esta compañía',
            ], 409);
        }

        $membership = OrgCompanyUser::create([
            'user_id' => $user->id,
            'org_company_id' => $company->id,
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return response()->json($membership, 201);
    }

    /**
     * 👁️ Ver detalle de un miembro del equipo y sus PERMISOS (Spatie)
     */
    public function show(string $uid, $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->with(['user.profile'])
            ->firstOrFail();

        // Configuramos el contexto de Spatie para esta empresa
        setPermissionsTeamId($company->id);

        return response()->json([
            'member_info' => $member,
            'spatie_data' => [
                'role' => $member->role,
                // Obtenemos solo los nombres de los permisos activos
                'active_permissions' => $member->user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * ✏️ Actualizar Rol y Permisos Granulares (Workspace)
     */
    public function update(Request $request, string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->with('user')
            ->firstOrFail();

        $data = $request->validate([
            // Validamos que el rol sea admin o member (como en tu DB)
            'role' => 'sometimes|string|in:admin,member',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
        ]);

        // 1. Actualizar rol base en la tabla de la compañía (org_company_users)
        if (isset($data['role'])) {
            $member->update(['role' => $data['role']]);
        }

        // 2. Sincronizar con Spatie (Roles y Permisos del Workspace)
        if ($member->role !== 'owner') {
            setPermissionsTeamId($company->id);

            // Mapeamos el rol de la compañía al rol de Spatie (tu DB de Spatie tiene 'admin' y 'user')
            $spatieRole = ($member->role === 'admin') ? 'admin' : 'user';

            // Asignamos el rol de Spatie
            $member->user->syncRoles([$spatieRole]);

            // Si envían permisos específicos, los sincronizamos
            if (isset($data['permissions'])) {
                $member->user->syncPermissions($data['permissions']);
            }
        }

        return response()->json([
            'message' => 'Accesos y permisos actualizados correctamente',
            'member' => $member,
        ]);
    }

    
    /**
     * ❌ Eliminar usuario de la compañía
     * (hard delete)
     */
    public function destroy(string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $member->delete();

        return response()->json([
            'message' => 'Usuario eliminado de la compañía',
        ]);
    }
}
