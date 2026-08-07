<?php

namespace App\Http\Controllers\Api\Partner\InvestorReady;

use App\Http\Controllers\Controller;
use App\Services\RealEstate\RealtorService;
use Illuminate\Http\Request;

class ExternalPropertyController extends Controller
{
    protected RealtorService $realtorService;

    public function __construct(RealtorService $realtorService)
    {
        $this->realtorService = $realtorService;
    }

    public function index(Request $request)
    {
        // 'location' es obligatorio para la API de Realtor (Ej. city:Miami, FL)
        $location = $request->get('location', 'city:Miami, FL');
        
        $params = [
            'location' => $location,
            'resultsPerPage' => $request->get('resultsPerPage', 16),
            'page' => $request->get('page', 1),
            'sortBy' => $request->get('sortBy', 'relevance'),
        ];

        // Filtros opcionales si el usuario los envía
        if ($request->filled('propertyType')) $params['propertyType'] = $request->get('propertyType');
        if ($request->filled('prices')) $params['prices'] = $request->get('prices');
        if ($request->filled('bedrooms')) $params['bedrooms'] = $request->get('bedrooms');
        if ($request->filled('bathrooms')) $params['bathrooms'] = $request->get('bathrooms');

        // Validar si es venta o renta
        $type = $request->get('type', 'buy'); // 'buy' o 'rent'
        $results = $type === 'rent' 
            ? $this->realtorService->searchRent($params) 
            : $this->realtorService->searchBuy($params);

        if (!$results) {
            return response()->json(['message' => 'No se pudieron recuperar las propiedades.'], 500);
        }

        return response()->json(['message' => 'Propiedades obtenidas con éxito.', 'data' => $results], 200);
    }

    public function autoComplete(Request $request, RealtorService $realtorService)
    {
        $request->validate([
            'input' => 'required|string|min:2'
        ]);

        $result = $realtorService->autoComplete([
            'input' => $request->input('input')
        ]);

        return response()->json($result);
    }
    
}