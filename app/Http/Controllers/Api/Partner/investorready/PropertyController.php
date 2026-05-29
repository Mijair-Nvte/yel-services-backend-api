<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgProperty;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Listar todas las propiedades del inversionista autenticado
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $properties = OrgProperty::where('user_id', $user->id)
                ->latest()
                ->get();

            return response()->json(['data' => $properties], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar tus propiedades.'], 500);
        }
    }

    /**
     * Ver el detalle de una propiedad específica del inversionista
     */
    public function show(Request $request, string $uid)
    {
        try {
            $user = $request->user();

            $property = OrgProperty::where('user_id', $user->id)
                ->where('uid', $uid)
                ->firstOrFail();

            return response()->json(['data' => $property], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Propiedad no encontrada o no tienes acceso.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar la propiedad.'], 500);
        }
    }
}
