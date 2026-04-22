<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\OrgCompany;
use App\Models\User;
use Illuminate\Http\Request;

class OrgMemberController extends Controller
{
    use AuthorizesWorkspace;

    // Obtener todos los miembros (Para la tabla)
    public function index(string $uid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        setPermissionsTeamId($company->id);
        
        $members = $company->users()->with('roles')->get()->map(function ($user) use ($company) {
            return [
                'id' => $user->id,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'role' => $user->id === $company->owner_id ? 'owner' : ($user->roles->first()->name ?? 'member'),
                'created_at' => $user->pivot->created_at,
            ];
        });

        return response()->json($members);
    }

    // Obtener un miembro específico (Para la vista de edición)
    public function show(string $uid, int $memberId)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = $company->users()->findOrFail($memberId);
        
        setPermissionsTeamId($company->id);

        return response()->json([
            'member_info' => [
                'user' => [
                    'name' => $member->name,
                    'email' => $member->email,
                    // Si tienes el modelo UserProfile para el teléfono, agrégalo aquí
                    // 'phone' => $member->profile->phone ?? '',
                ]
            ],
            'spatie_data' => [
                'role' => $member->roles->first()->name ?? 'member',
                'active_permissions' => $member->permissions->pluck('name'),
            ]
        ]);
    }

    // Actualizar rol, permisos y datos básicos (Cuando le dan "Guardar Cambios")
    public function update(Request $request, string $uid, int $memberId)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        $member = $company->users()->findOrFail($memberId);

        // Protección: Nadie modifica al Owner
        if ($member->id === $company->owner_id) {
            return response()->json(['message' => 'No puedes modificar los accesos del dueño legal.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:30',
            'role' => 'required|string|in:admin,member',
            'permissions' => 'array',
            'permissions.*' => 'string'
        ]);

        // Actualizamos el nombre en el modelo User
        if (isset($validated['name'])) {
            $member->update(['name' => $validated['name']]);
        }

        // Si guardas el teléfono en otra tabla (ej. UserProfile), iría aquí
        // if (isset($validated['phone'])) {
        //     $member->profile()->updateOrCreate(['user_id' => $member->id], ['phone' => $validated['phone']]);
        // }

        setPermissionsTeamId($company->id);

        // 1. Sincronizamos el Rol
        $member->syncRoles([$validated['role']]);

        // 2. Sincronizamos los Permisos
        if ($validated['role'] === 'admin') {
            $member->syncPermissions([]); // El admin ya tiene todo por defecto
        } else {
            $member->syncPermissions($validated['permissions'] ?? []);
        }

        return response()->json(['message' => 'Perfil y permisos actualizados correctamente.']);
    }

    // Eliminar miembro
    public function destroy(string $uid, int $memberId)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);

        if ($memberId === $company->owner_id) {
            return response()->json(['message' => 'No puedes eliminar al dueño de la compañía.'], 403);
        }

        // Lo removemos de la tabla pivote org_company_users
        $company->users()->detach($memberId);

        return response()->json(['message' => 'Usuario eliminado del equipo.']);
    }
}