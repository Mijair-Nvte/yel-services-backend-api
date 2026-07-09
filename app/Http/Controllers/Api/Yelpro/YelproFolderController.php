<?php

namespace App\Http\Controllers\Api\Yelpro;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\OrgCompany;
use Illuminate\Http\Request;

class YelproFolderController extends Controller
{
    /**
     * Muestra las carpetas compartidas con YelPro para la compañía actual.
     */
    public function index(Request $request, $companyUid) // 👈 Recibimos el parámetro de la ruta aquí
    {
        // 1. Buscar la compañía usando el UID de la URL
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 2. Obtener solo las carpetas compartidas con 'yelpro' para este tenant
        $folders = Folder::where('org_company_id', $company->id)
            ->forPlatform('yel_pro') // 👈 El Scope mágico
            ->with(['documents' => function ($query) {
                // Ordenamos los documentos del más reciente al más antiguo
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy('order', 'asc') // Opcional, si usas el campo 'order'
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $folders
        ]);
    }
}