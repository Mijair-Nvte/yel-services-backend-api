<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgProject;
use App\Models\OrgProjectTask;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrgProjectTaskController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todas las tareas de un proyecto específico
     */
    public function index(Request $request, string $uid, string $projectUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $tasks = OrgProjectTask::where('org_project_id', $project->id)
                ->with(['assignee:id,name,email', 'event:id,uid,title,starts_at'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar las tareas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 📝 Crear una nueva tarea dentro del proyecto
     */
    public function store(Request $request, string $uid, string $projectUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'department' => 'required|string', 
                'platform' => 'nullable|string',     
                'task_type' => 'nullable|string',    
                'phase' => 'nullable|string',         // 👈 Fase flexible
                'is_milestone' => 'nullable|boolean', // 👈 Es un hito
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'status' => 'nullable|in:pending,in_progress,review,completed',
                'assigned_to' => 'nullable|exists:users,id',
                'org_event_id' => 'nullable|exists:org_events,id',
                'metadata' => 'nullable|array',      // 👈 Notas y datos flexibles
            ]);

            $task = OrgProjectTask::create([
                'org_company_id' => $company->id,
                'org_project_id' => $project->id,
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'department' => $validatedData['department'],
                'platform' => $validatedData['platform'] ?? null,
                'task_type' => $validatedData['task_type'] ?? null,
                'phase' => $validatedData['phase'] ?? null,
                'is_milestone' => $validatedData['is_milestone'] ?? false,
                'start_date' => $validatedData['start_date'] ?? null,
                'end_date' => $validatedData['end_date'] ?? null,
                'status' => $validatedData['status'] ?? 'pending',
                'assigned_to' => $validatedData['assigned_to'] ?? null,
                'org_event_id' => $validatedData['org_event_id'] ?? null,
                'metadata' => $validatedData['metadata'] ?? null,
            ]);

            return response()->json($task->load(['assignee:id,name,email', 'event:id,uid,title']), 201);
        } catch (\Exception $e) {
            \Log::error('🔥 Error al crear tarea de proyecto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear la tarea: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✏️ Actualizar una tarea existente
     */
    public function update(Request $request, string $uid, string $projectUid, string $taskUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $task = OrgProjectTask::where('uid', $taskUid)
                ->where('org_project_id', $project->id)
                ->firstOrFail();

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'department' => 'sometimes|required|string',
                'platform' => 'nullable|string',
                'task_type' => 'nullable|string',
                'phase' => 'nullable|string',
                'is_milestone' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'status' => 'sometimes|required|in:pending,in_progress,review,completed',
                'assigned_to' => 'nullable|exists:users,id',
                'org_event_id' => 'nullable|exists:org_events,id',
                'metadata' => 'nullable|array',
            ]);

            $task->update($validatedData);

            return response()->json($task->load(['assignee:id,name,email', 'event:id,uid,title']));
        } catch (\Exception $e) {
            \Log::error('🔥 Error al actualizar tarea: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar la tarea.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar una tarea
     */
    public function destroy(string $uid, string $projectUid, string $taskUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_projects');

            $project = OrgProject::where('uid', $projectUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $task = OrgProjectTask::where('uid', $taskUid)
                ->where('org_project_id', $project->id)
                ->firstOrFail();

            $task->delete();

            return response()->json(['message' => 'Tarea eliminada correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la tarea.'], 500);
        }
    }
}