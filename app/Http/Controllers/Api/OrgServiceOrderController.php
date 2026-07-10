<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrgServiceOrderController extends Controller
{
    use AuthorizesWorkspace;

    /**
     * 📌 Listar todas las órdenes de servicio (Para el Kanban / Tabla)
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            // Cargamos las relaciones clave para que el frontend tenga todo el contexto
            $orders = OrgServiceOrder::with([
                'customer:id,first_name,last_name,email,phone',
                'service:id,name,cover_image',
                'assignee:id,name,email',
                'followers:id,name,email',
                'sale:id,uid,total_amount',
            ])
                ->where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $orders,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener las órdenes de servicio.'], 500);
        }
    }

    /**
     * 📌 Mostrar los detalles de una orden específica
     */
    public function show(string $uid, string $orderUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $order = OrgServiceOrder::with([
                'customer',
                'service',
                'assignee',
                'followers',
                'sale',
            ])
                ->where('uid', $orderUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json([
                'data' => $order,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Orden de servicio no encontrada.'], 404);
        }
    }

    /**
     * 📌 Actualizar una orden (Cambiar estado, reasignar Owner, editar Followers o Metadata)
     */
    public function update(Request $request, string $uid, string $orderUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $order = OrgServiceOrder::where('uid', $orderUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $validated = $request->validate([
                'status' => 'sometimes|required|string|in:pending,in_progress,waiting_client,review,completed,cancelled',
                'assigned_to' => 'nullable|exists:users,id',
                'metadata' => 'nullable|array', // Para guardar links de Google Drive, notas internas, etc.
                'follower_ids' => 'nullable|array',
                'follower_ids.*' => 'exists:users,id',
            ]);

            // Actualizamos los campos directos de la tabla
            $order->update($request->only(['status', 'assigned_to', 'metadata']));

            // Si se envían los followers, sincronizamos la tabla pivote
            if ($request->has('follower_ids')) {
                // Sincroniza exactamente con el arreglo de IDs que envíe el frontend
                $order->followers()->sync($request->follower_ids);
            }

            // Recargamos el modelo con sus relaciones actualizadas para devolverlo al frontend
            $order->load(['assignee', 'followers']);

            return response()->json([
                'message' => 'Orden actualizada correctamente',
                'data' => $order,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la orden de servicio.'], 500);
        }
    }

    /**
     * 📌 Eliminar una orden (Soft Delete)
     */
    public function destroy(string $uid, string $orderUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);

            $order = OrgServiceOrder::where('uid', $orderUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $order->delete();

            return response()->json(['message' => 'Orden de servicio eliminada exitosamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la orden de servicio.'], 500);
        }
    }
}
