<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgProject;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrgProjectController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📊 Listar todos los proyectos de la compañía
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_projects');

            $projects = OrgProject::where('org_company_id', $company->id)
                ->with('creator:id,name,email') // 👈 CORREGIDO AQUÍ
                ->withCount('tasks') // Trae el total de tareas asociadas
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($projects);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar los proyectos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 📝 Crear un nuevo proyecto
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'nullable|in:draft,active,completed,archived',
            ]);

            $project = OrgProject::create([
                'org_company_id' => $company->id,
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'start_date' => $validatedData['start_date'] ?? null,
                'end_date' => $validatedData['end_date'] ?? null,
                'status' => $validatedData['status'] ?? 'draft',
                'created_by' => Auth::id(),
            ]);

            return response()->json($project, 201);
        } catch (\Exception $e) {
            \Log::error('🔥 Error al crear proyecto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear el proyecto.'], 500);
        }
    }

    /**
     * 🔍 Mostrar detalle de un proyecto específico
     */
    public function show(string $uid, string $projectUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->with('creator:id,name,email') // 👈 CORREGIDO AQUÍ TAMBIÉN
                ->firstOrFail();

            return response()->json($project);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proyecto no encontrado.'], 404);
        }
    }

    /**
     * ✏️ Actualizar un proyecto
     */
    public function update(Request $request, string $uid, string $projectUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'sometimes|required|in:draft,active,completed,archived',
            ]);

            $project->update($validatedData);

            return response()->json($project);
        } catch (\Exception $e) {
            \Log::error('🔥 Error al actualizar proyecto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar el proyecto.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar un proyecto
     */
    public function destroy(string $uid, string $projectUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $project->delete();

            return response()->json(['message' => 'Proyecto eliminado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el proyecto.'], 500);
        }
    }
}