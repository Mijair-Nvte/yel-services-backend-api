<?php

namespace App\Http\Controllers\Api\Yelpro;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgFeedback;
use Illuminate\Http\Request;

class YelproFeedbackController extends Controller
{
    /**
     * Almacena un nuevo feedback enviado desde Yel Pro
     */
    public function store(Request $request, string $companyUid)
    {
        // 1. Validar la petición
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:bug,feature_request,general_comment,help',
            'source' => 'required|in:yelpro,yelinvestor',
        ]);

        // 2. Obtener la compañía usando el UID de la ruta
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 3. Crear el feedback asociado al usuario autenticado y a la empresa
        $feedback = OrgFeedback::create([
            'org_company_id' => $company->id,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'source' => $validated['source'],
            'status' => 'pending',
        ]);

        // TODO: (Opcional) Disparar evento de notificación a administradores
        // event(new \App\Events\YelproFeedbackSubmitted($feedback));

        return response()->json([
            'message' => 'Tu mensaje ha sido enviado exitosamente. ¡Gracias por ayudarnos a mejorar!',
            'data' => $feedback,
        ], 201);
    }

    public function index(Request $request, string $companyUid)
    {

        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $query = OrgFeedback::where('org_company_id', $company->id)
            ->where('user_id', auth()->id());

        // Si mandan el parámetro source en la URL (ej: ?source=yelinvestor), lo filtramos
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $feedbacks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $feedbacks,
        ]);
    }
}
