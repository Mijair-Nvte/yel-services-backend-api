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
     * 👁️ Ver detalle de un miembro del equipo
     */
    public function show(string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->with([
                'user.profile',
                'user.areaAssignments.area',
                'user.areaAssignments.position',
            ])
            ->firstOrFail();

        return response()->json($member);
    }

    /**
     * ✏️ Actualizar rol o estado del usuario en la compañía
     */
    public function update(Request $request, string $uid, int $id)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = OrgCompanyUser::where('org_company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'role' => 'sometimes|string|in:owner,admin,member',
            'is_active' => 'sometimes|boolean',
        ]);

        $member->update($data);

        return response()->json($member);
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
