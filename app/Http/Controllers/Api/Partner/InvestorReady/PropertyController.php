<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PropertyController extends Controller
{
    /**
     * Listar todas las propiedades del inversionista autenticado para una compañía
     */
    public function index(Request $request, string $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = $request->user();

            $properties = OrgProperty::where('org_company_id', $company->id)
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            return response()->json(['data' => $properties], 200);

        } catch (\Exception $e) {
            Log::error('Error al listar propiedades: '.$e->getMessage());
            return response()->json(['message' => 'Error al cargar tus propiedades.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ver el detalle de una propiedad específica
     */
    public function show(Request $request, string $companyUid, string $propertyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = $request->user();

            $property = OrgProperty::where('org_company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('uid', $propertyUid)
                ->firstOrFail();

            return response()->json(['data' => $property], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Propiedad no encontrada o no tienes acceso.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar la propiedad.'], 500);
        }
    }

    /**
     * Crear una nueva propiedad
     */
    public function store(Request $request, string $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'portfolio_type' => 'required|string|max:100',
                'property_type' => 'nullable|string|max:100',
                'closing_type' => 'required|in:yel_internal,external',
                
                // Nuevos campos agregados
                'borrower_first_name' => 'nullable|string|max:255',
                'borrower_last_name' => 'nullable|string|max:255',
                'co_borrower_first_name' => 'nullable|string|max:255',
                'co_borrower_last_name' => 'nullable|string|max:255',
                'borrower_mobile' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip' => 'nullable|string|max:20',
                'occupancy' => 'nullable|string|max:100',
                
                'status' => 'nullable|string|max:100',
                'closed_at' => 'nullable|date',
               'image_path' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Errores de validación.', 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $data = $validator->validated();
            
            $data['uid'] = 'prop_' . strtolower(Str::random(25));
            $data['user_id'] = $user->id;
            $data['org_company_id'] = $company->id; 

            $property = OrgProperty::create($data);

            return response()->json([
                'message' => 'Propiedad registrada exitosamente.',
                'data' => $property
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear propiedad: '.$e->getMessage());
            return response()->json(['message' => 'Error al crear la propiedad.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar una propiedad existente
     */
    public function update(Request $request, string $companyUid, string $propertyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = $request->user();

            $property = OrgProperty::where('org_company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('uid', $propertyUid)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'portfolio_type' => 'sometimes|required|string|max:100',
                'property_type' => 'nullable|string|max:100',
                'closing_type' => 'sometimes|required|in:yel_internal,external',
                
                // Nuevos campos agregados
                'borrower_first_name' => 'nullable|string|max:255',
                'borrower_last_name' => 'nullable|string|max:255',
                'co_borrower_first_name' => 'nullable|string|max:255',
                'co_borrower_last_name' => 'nullable|string|max:255',
                'borrower_mobile' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip' => 'nullable|string|max:20',
                'occupancy' => 'nullable|string|max:100',
                
                'status' => 'nullable|string|max:100',
                'closed_at' => 'nullable|date',
                'image_path' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Errores de validación.', 'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

        

            $property->update($data);

            return response()->json([
                'message' => 'Propiedad actualizada exitosamente.',
                'data' => $property->fresh()
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Propiedad no encontrada o no tienes acceso.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la propiedad.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una propiedad
     */
    public function destroy(Request $request, string $companyUid, string $propertyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = $request->user();

            $property = OrgProperty::where('org_company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('uid', $propertyUid)
                ->firstOrFail();

            $property->delete();

            return response()->json(['message' => 'Propiedad eliminada exitosamente.'], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Propiedad no encontrada o no tienes acceso.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la propiedad.', 'error' => $e->getMessage()], 500);
        }
    }
}