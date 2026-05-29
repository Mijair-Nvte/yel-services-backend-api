<?php

namespace App\Http\Controllers\Api\LoanApplication;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\OrgCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanApplicationController extends Controller
{
    /**
     * Obtiene la solicitud activa del usuario en la compañía actual.
     * Si no tiene ninguna, le crea una en estado 'draft' (borrador).
     */
    public function myApplication(Request $request, $uid)
    {
        $user = Auth::user();
        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Buscamos si el usuario ya tiene un borrador (draft) o una en revisión
        $application = LoanApplication::firstOrCreate(
            [
                'org_company_id' => $company->id,
                'user_id' => $user->id,
                'status' => 'draft', // Solo buscamos/creamos borradores
            ],
            [
                'current_step' => 1,
                'progress_percentage' => 0.00,
            ]
        );

        // Cargamos las secciones que ya haya contestado para mandarlas al frontend
        $application->load('sections');

        return response()->json([
            'success' => true,
            'application' => $application,
        ]);
    }

    /**
     * Guarda o actualiza una sección específica del formulario
     */
    public function saveSection(Request $request, $uid, $loanUid)
    {
        $request->validate([
            'section_id' => 'required|integer|between:1,8',
            'data' => 'required|array',
        ]);

        $company = OrgCompany::where('uid', $uid)->firstOrFail();

        // Asegurarnos de que la solicitud pertenezca al usuario autenticado y a la empresa actual
        $application = LoanApplication::where('uid', $loanUid)
            ->where('org_company_id', $company->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Guardamos o actualizamos la sección (updateOrCreate busca por section_id)
        $section = $application->sections()->updateOrCreate(
            ['section_id' => $request->section_id],
            [
                'status' => 'completed',
                'data' => $request->data,
            ]
        );

        // ---------------------------------------------------------
        // RECALCULAR EL PROGRESO GLOBAL DE LA SOLICITUD
        // ---------------------------------------------------------
        $totalSections = 8; // Tienes 8 pasos definidos en el frontend
        $completedCount = $application->sections()->where('status', 'completed')->count();
        $progress = ($completedCount / $totalSections) * 100;

        // Actualizamos el paso actual (lo preparamos para el siguiente) y el progreso
        $application->update([
            'current_step' => $request->section_id < $totalSections ? $request->section_id + 1 : $totalSections,
            'progress_percentage' => $progress,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sección '.$request->section_id.' guardada correctamente.',
            'progress_percentage' => $application->progress_percentage,
            'current_step' => $application->current_step,
        ]);
    }
}
