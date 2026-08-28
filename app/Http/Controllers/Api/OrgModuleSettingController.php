<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgModuleSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrgModuleSettingController extends Controller
{
    // Agregamos los traits que usas para autorización y Spatie
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 👁️ Obtiene la configuración de un módulo específico.
     *
     * @param string $uid El UID de la compañía
     * @param string $moduleName El nombre del módulo (ej: 'loans', 'insurances')
     */
    public function show(string $uid, string $moduleName)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual (Verifica membresía y activa Spatie para la empresa)
            $this->authorizeWorkspace($company);
            
            // Opcional: Aquí podrías validar un permiso para VER las configuraciones
            // $this->authorize('view_company_settings');

            // Buscamos la configuración de ese módulo para la empresa
            $setting = OrgModuleSetting::where('org_company_id', $company->id)
                ->where('module_name', $moduleName)
                ->first();

            // Si no existe, devolvemos una estructura base por defecto
            return response()->json([
                'data' => $setting ?? [
                    'org_company_id' => $company->id,
                    'module_name' => $moduleName,
                    'settings' => null, 
                    'is_active' => true,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la configuración del módulo.'], 500);
        }
    }

    /**
     * ✏️ Crea o actualiza la configuración de un módulo (Idempotente).
     *
     * @param Request $request
     * @param string $uid El UID de la compañía
     * @param string $moduleName El nombre del módulo
     */
    public function update(Request $request, string $uid, string $moduleName)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            
            // 🛡️ Seguridad de Roles (Spatie). 
            // NOTA: Ajusta 'manage_company_settings' al nombre exacto del permiso que uses para tus Admins/Owners
            $this->authorize('manage_company_settings');

            // 1. Validaciones directas en el controlador
            $validatedData = $request->validate([
                'settings' => 'nullable|array',
                'is_active' => 'sometimes|boolean',
            ]);

            // 2. updateOrCreate: Si existe lo actualiza, si no, lo crea.
            $setting = OrgModuleSetting::updateOrCreate(
                [
                    'org_company_id' => $company->id,
                    'module_name' => $moduleName,
                ],
                [
                    'settings' => $validatedData['settings'] ?? [],
                    'is_active' => $validatedData['is_active'] ?? true,
                ]
            );

            return response()->json([
                'message' => 'Configuración guardada exitosamente.',
                'data' => $setting
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => 'No tienes permisos para modificar las configuraciones.'], 403);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la configuración del módulo.'], 500);
        }
    }
}