<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrgMemberAccessController extends Controller
{
    use AuthorizesWorkspace, AuthorizesRequests;

    /**
     * Activa o desactiva un permiso específico para un miembro al instante
     */
    public function togglePermission(Request $request, string $uid, int $memberId)
    {
        try {
            // 1. Validar el entorno de la empresa
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            
            // 2. Seguridad estricta: Solo administradores (los que tienen manage_team) pueden hacer esto
            $this->authorize('manage_team');

            // 3. Buscar al miembro a modificar
            $member = $company->users()->findOrFail($memberId);

            // Protección: No se pueden modificar los permisos del dueño legal
            if ($member->id === $company->owner_id) {
                return response()->json(['message' => 'No puedes modificar los permisos del dueño de la compañía.'], 403);
            }

            // 4. Validar los datos del frontend (el switch)
            $validated = $request->validate([
                'permission' => 'required|string', // ej: 'manage_notices', 'view_sales'
                'is_active' => 'required|boolean'  // true (prendido) o false (apagado)
            ]);

            // 5. Configurar Spatie para que los cambios solo afecten a esta empresa
            setPermissionsTeamId($company->id);

            // Protección: Si el usuario es admin, ya tiene acceso a todo. 
            // No tiene sentido darle permisos individuales.
            if ($member->hasRole('admin')) {
                return response()->json([
                    'message' => 'Un administrador ya tiene acceso total. No es necesario asignar permisos individuales.'
                ], 400);
            }

            // 6. La magia: Prender o apagar el permiso
            if ($validated['is_active']) {
                $member->givePermissionTo($validated['permission']);
            } else {
                $member->revokePermissionTo($validated['permission']);
            }

            return response()->json([
                'message' => 'Permiso actualizado correctamente.',
                // Devolvemos la lista actualizada para que tu frontend Next.js actualice sus estados
                'active_permissions' => $member->permissions->pluck('name') 
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Member or Company not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized access. Only administrators can perform this action.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the permission.', 'error' => $e->getMessage()], 500);
        }
    }
}