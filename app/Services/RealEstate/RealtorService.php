<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RealtorService
{
    protected string $apiKey;
    protected string $apiHost;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('RAPIDAPI_KEY');
        $this->apiHost = env('RAPIDAPI_HOST', 'realtor-search.p.rapidapi.com');
        $this->baseUrl = "https://{$this->apiHost}";
    }

    /**
     * PASO 1: Autocompletado de ubicaciones
     * Devuelve una lista de ubicaciones válidas con sus IDs geográficos.
     */
   /**
     * PASO 1: Autocompletado de ubicaciones
     */
    public function autoComplete(array $params)
    {
        // Cambiamos a la ruta exacta de tu cURL
        return $this->makeRequest('/properties/auto-complete', $params);
    }

    /**
     * PASO 2: Buscar propiedades en venta dinámicamente
     */
    public function searchBuy(array $params)
    {
        return $this->makeRequest('/properties/search-buy', $params);
    }

    /**
     * PASO 2: Buscar propiedades en renta dinámicamente
     */
    public function searchRent(array $params)
    {
        return $this->makeRequest('/properties/search-rent', $params);
    }

    private function makeRequest(string $endpoint, array $params)
    {
        try {
            $response = Http::withHeaders([
                'content-type' => 'application/json',
                'x-rapidapi-key' => $this->apiKey,
                'x-rapidapi-host' => $this->apiHost,
            ])->get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Realtor API Error ({$endpoint}): " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Realtor Service Exception: ' . $e->getMessage());
            return null;
        }
    }
}