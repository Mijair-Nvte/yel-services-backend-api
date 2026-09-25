<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCustomer; // Asegúrate de tener este modelo creado
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgCustomerController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todos los clientes de la compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_customers');

            // Listar clientes ordenados por los más recientes (puedes cambiar a paginate() si son muchos)
            $customers = OrgCustomer::where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $customers], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los clientes.'], 500);
        }
    }

    /**
     * ➕ Crear un nuevo cliente
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_customers');

            $validated = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name'  => 'nullable|string|max:100',
                'email'      => 'nullable|email|max:255',
                'phone'      => 'nullable|string|max:20',
                'user_id'    => 'nullable|exists:users,id', // Por si se vincula a una cuenta existente
                'metadata'   => 'nullable|array', // Para tags, source, custom fields, etc.
            ]);

            // Asignar el ID de la empresa y generar un UID único
            $validated['org_company_id'] = $company->id;
            $validated['uid'] = Str::uuid()->toString(); // Asumiendo que no usas un trait auto-generador de UUIDs en el modelo

            $customer = OrgCustomer::create($validated);

            return response()->json([
                'message' => 'Cliente creado correctamente.',
                'data' => $customer
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el cliente.'], 500);
        }
    }

    /**
     * 👁️ Ver el detalle de un cliente específico
     */
    public function show(string $uid, string $customerUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_customers');

            // Se puede hacer eager loading (with) si el cliente tiene relaciones como ventas, préstamos, etc.
            $customer = OrgCustomer::where('uid', $customerUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $customer], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el cliente o no existe.'], 404);
        }
    }

    /**
     * ✏️ Actualizar un cliente
     */
    public function update(Request $request, string $uid, string $customerUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_customers');

            $customer = OrgCustomer::where('uid', $customerUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:100',
                'last_name'  => 'nullable|string|max:100',
                'email'      => 'nullable|email|max:255',
                'phone'      => 'nullable|string|max:20',
                'user_id'    => 'nullable|exists:users,id',
                'metadata'   => 'nullable|array',
            ]);

            $customer->update($validated);

            return response()->json([
                'message' => 'Cliente actualizado correctamente.',
                'data' => $customer
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el cliente.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar un cliente
     */
    public function destroy(string $uid, string $customerUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_customers');

            $customer = OrgCustomer::where('uid', $customerUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $customer->delete(); // Esto ejecutará un Soft Delete si tu modelo usa el trait SoftDeletes

            return response()->json(['message' => 'Cliente eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el cliente.'], 500);
        }
    }
}