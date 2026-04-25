<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgMemberController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * Obtener todos los miembros (Para la tabla)
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_team');

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

            return response()->json($members, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Company not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching members.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un miembro específico (Para la vista de edición)
     */
    public function show(string $uid, int $memberId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_team');

            $member = $company->users()->findOrFail($memberId);

            setPermissionsTeamId($company->id);

            return response()->json([
                'member_info' => [
                    'user' => [
                        'name' => $member->name,
                        'email' => $member->email,
                    ],
                ],
                'spatie_data' => [
                    'role' => $member->roles->first()->name ?? 'member',
                    'active_permissions' => $member->permissions->pluck('name'),
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Member or Company not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching the member.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar ROL PRINCIPAL (Admin/Member)
     */
    public function update(Request $request, string $uid, int $memberId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_team');

            $member = $company->users()->findOrFail($memberId);

            if ($member->id === $company->owner_id) {
                return response()->json(['message' => 'No puedes modificar los accesos del dueño legal.'], 403);
            }

            $validated = $request->validate([
                'role' => 'required|string|in:admin,member',
            ]);

            setPermissionsTeamId($company->id);

            // Sincronizamos el Rol Principal
            $member->syncRoles([$validated['role']]);

            // Si lo subieron a Admin, le limpiamos los permisos directos extra (ya no los necesita)
            if ($validated['role'] === 'admin') {
                $member->syncPermissions([]);
            }

            return response()->json(['message' => 'Rol del usuario actualizado correctamente.'], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Member or Company not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the member.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar miembro
     */
    public function destroy(string $uid, int $memberId)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_team');

            if ($memberId === $company->owner_id) {
                return response()->json(['message' => 'No puedes eliminar al dueño de la compañía.'], 403);
            }

            $company->users()->detach($memberId);

            setPermissionsTeamId($company->id);
            $member = User::find($memberId);
            if ($member) {
                $member->syncRoles([]);
                $member->syncPermissions([]);
            }

            return response()->json(['message' => 'Usuario eliminado del equipo correctamente.'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Member or Company not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while removing the member.', 'error' => $e->getMessage()], 500);
        }
    }
}
