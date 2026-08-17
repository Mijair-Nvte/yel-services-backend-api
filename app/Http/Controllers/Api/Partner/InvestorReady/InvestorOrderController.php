<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VMisPedidos;

class InvestorOrderController extends Controller
{
    /**
     * Obtiene la lista de pedidos/servicios comprados por el usuario autenticado.
     */
    public function index(Request $request, $companyUid)
    {
        // 1. Obtenemos el usuario autenticado
        $user = $request->user();

        // 2. Consultamos la vista filtrando por su user_id
        $pedidos = VMisPedidos::where('user_id', $user->id)
            ->orderBy('purchase_date', 'desc')
            ->get();

        /* 
         * NOTA: Si en el futuro necesitas que estos pedidos sean SOLO los de la compañía actual ($companyUid), 
         * tendríamos que agregar el company_id a la vista SQL y hacer el filtro aquí.
         * Por ahora, le mostrará todas sus compras al usuario.
         */

        // 3. Devolvemos la respuesta en formato JSON
        return response()->json([
            'success' => true,
            'message' => 'Mis pedidos recuperados exitosamente.',
            'data'    => $pedidos
        ]);
    }
}