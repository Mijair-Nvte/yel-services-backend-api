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
            $this->authorizeWorkspace($company);
            $this->authorize('view_customers');

            $customers = OrgCustomer::where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $customers], 200);
        } catch (\Exception$e) {
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
                'last_name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'user_id' => 'nullable|exists:users,id', // Por si se vincula a una cuenta existente
                'metadata' => 'nullable|array', // Para tags, source, custom fields, etc.
            ]);

            // Asignar el ID de la empresa y generar un UID único
            $validated['org_company_id'] = $company->id;
            $validated['uid'] = Str::uuid()->toString(); // Asumiendo que no usas un trait auto-generador de UUIDs en el modelo

            $customer = OrgCustomer::create($validated);

            return response()->json([
                'message' => 'Cliente creado correctamente.',
                'data' => $customer,
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

            $customer = OrgCustomer::where('uid', $customerUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $customer], 200);
        } catch (\Exception$e) {
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
                'last_name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'user_id' => 'nullable|exists:users,id',
                'metadata' => 'nullable|array',
            ]);

            $customer->update($validated);

            return response()->json([
                'message' => 'Cliente actualizado correctamente.',
                'data' => $customer,
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

    /**
     * Tab: Solicitudes de Préstamos (Loans)
     */
    public function getLoans(string $uid, string $customerUid)
    {
        try {
            $customer = $this->getValidCustomer($uid, $customerUid);

            // Traemos solo los préstamos de este cliente
            $loans = $customer->loanApplications()->orderBy('created_at', 'desc')->get();

            return response()->json(['data' => $loans], 200);
        } catch (\Exception$e) {
            return response()->json(['message' => 'Error al obtener los préstamos.'], 500);
        }
    }

    /**
     * Tab: Solicitudes de Seguros (Insurances)
     */
    public function getInsurances(string $uid, string $customerUid)
    {
        try {
            $customer = $this->getValidCustomer($uid, $customerUid);

            $insurances = $customer->insuranceApplications()->orderBy('created_at', 'desc')->get();

            return response()->json(['data' => $insurances], 200);
        } catch (\Exception$e) {
            return response()->json(['message' => 'Error al obtener los seguros.'], 500);
        }
    }

    /**
     * Tab: Registro a Eventos (Events)
     */
    public function getEvents(string $uid, string $customerUid)
    {
        try {
            $customer = $this->getValidCustomer($uid, $customerUid);

            $events = $customer->eventRegistrations()->with('event')->orderBy('created_at', 'desc')->get();

            return response()->json(['data' => $events], 200);
        } catch (\Exception$e) {
            return response()->json(['message' => 'Error al obtener los eventos.'], 500);
        }
    }

    /**
     * Tab: Órdenes de Servicio
     */
  public function getServiceOrders(string $uid, string $customerUid)
    {
        try {
            $customer = $this->getValidCustomer($uid, $customerUid);

         
            $orders = $customer->serviceOrders()->with(['service', 'sale'])->orderBy('created_at', 'desc')->get();

            return response()->json(['data' => $orders], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener las órdenes de servicio.'], 500);
        }
    }

    // =========================================================================
    // 🛠️ MÉTODOS PRIVADOS (Helpers)
    // =========================================================================

    /**
     * Helper para validar permisos y obtener el cliente de forma segura y no repetir código.
     */
    private function getValidCustomer(string $uid, string $customerUid)
    {
        $company = OrgCompany::where('uid', $uid)->firstOrFail();
        $this->authorizeWorkspace($company);
        $this->authorize('view_customers'); // O el permiso específico que uses

        return OrgCustomer::where('uid', $customerUid)
            ->where('org_company_id', $company->id)
            ->firstOrFail();
    }
}
