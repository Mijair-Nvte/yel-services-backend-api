<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrgCourseController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todos los cursos de la compañía con paginación y filtros
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_courses');

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            $query = OrgCourse::where('org_company_id', $company->id)
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $courses = $query->paginate($perPage);

            // KPIs rápidos para la vista principal
            $totalPublished = OrgCourse::where('org_company_id', $company->id)->where('status', 'published')->count();
            $totalDrafts = OrgCourse::where('org_company_id', $company->id)->where('status', 'draft')->count();

            return response()->json([
                'data' => $courses->items(),
                'meta' => [
                    'current_page' => $courses->currentPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                    'last_page' => $courses->lastPage(),
                    'kpis' => [
                        'total_published' => $totalPublished,
                        'total_drafts' => $totalDrafts,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los cursos.'], 500);
        }
    }

    /**
     * 👁️ Ver detalle de un curso específico
     */
    public function show(string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('view_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            return response()->json(['data' => $course], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Curso no encontrado.'], 404);
        }
    }

/**
     * ☁️ Generar URL Pre-firmada para subidas directas a R2 (Estructura Limpia por Curso)
     */
    public function presign(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'file_name' => 'required|string',
                'mime_type' => 'required|string',
                'type' => 'required|in:cover,preview',
                'course_uid' => 'required|string', // AHORA ES OBLIGATORIO
            ]);

            $fileName = $request->file_name;
            $mimeType = $request->mime_type;
            $type = $request->type;
            $courseUid = $request->course_uid;

            // Al ser obligatorio, forzamos la estructura estricta:
            // workspace_uid/courses/course_uid/covers/ O /previews/
            $folderName = $type === 'cover' ? 'covers' : 'previews';
            $path = "{$company->uid}/courses/{$courseUid}/{$folderName}/".time().'_'.Str::slug(pathinfo($fileName, PATHINFO_FILENAME)).'.'.pathinfo($fileName, PATHINFO_EXTENSION);

            $disk = Storage::disk('r2_public');
            $client = $disk->getClient();
            $bucket = $disk->getConfig()['bucket'];

            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $path,
                'ContentType' => $mimeType,
            ]);

            $signedRequest = $client->createPresignedRequest($command, '+15 minutes');
            $uploadUrl = (string) $signedRequest->getUri();
            $publicUrl = $disk->url($path);

            return response()->json([
                'upload_url' => $uploadUrl,
                'path' => $path,
                'public_url' => $publicUrl,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar la pre-firma: '.$e->getMessage()], 500);
        }
    }
    /**
     * ✅ Confirmar subida (Opcional)
     */
    public function confirm(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->path;
            $disk = Storage::disk('r2_public');

            if (! $disk->exists($path)) {
                return response()->json(['message' => 'El archivo no se encuentra en el almacenamiento.'], 404);
            }

            return response()->json([
                'message' => 'Archivo confirmado correctamente.',
                'public_url' => $disk->url($path),
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al confirmar el archivo.'], 500);
        }
    }

    /**
     * ➕ Crear un nuevo curso
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:draft,published,archived',
                'is_active' => 'boolean',
                'is_free' => 'boolean',
                'price' => 'nullable|numeric|min:0',
                'revenuecat_entitlement_id' => 'nullable|string|max:255',
                'cover_image_url' => 'nullable|string',
                'preview_video_url' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $exists = OrgCourse::where('org_company_id', $company->id)
                ->where('slug', $request->slug)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'El slug ya está en uso por otro curso en esta empresa.'], 422);
            }

            $course = OrgCourse::create([
                'uid' => 'crs_'.Str::random(16),
                'org_company_id' => $company->id,
                'created_by' => auth()->id(),
                'title' => $request->title,
                'slug' => $request->slug,
                'description' => $request->description,
                'status' => $request->status,
                'is_active' => $request->is_active ?? true,
                'is_free' => $request->is_free ?? true,
                'price' => $request->is_free ? null : $request->price,
                'revenuecat_entitlement_id' => $request->is_free ? null : $request->revenuecat_entitlement_id,
                'cover_image_url' => $request->cover_image_url,
                'preview_video_url' => $request->preview_video_url,
                'metadata' => $request->metadata,
            ]);

            return response()->json([
                'message' => 'Curso creado correctamente.',
                'data' => $course,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el curso: '.$e->getMessage()], 500);
        }
    }

    /**
     * ✏️ Actualizar un curso existente
     */
    public function update(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|in:draft,published,archived',
                'is_active' => 'boolean',
                'is_free' => 'boolean',
                'price' => 'nullable|numeric|min:0',
                'revenuecat_entitlement_id' => 'nullable|string|max:255',
                'cover_image_url' => 'nullable|string',
                'preview_video_url' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            if ($request->has('slug') && $request->slug !== $course->slug) {
                $exists = OrgCourse::where('org_company_id', $company->id)
                    ->where('slug', $request->slug)
                    ->exists();

                if ($exists) {
                    return response()->json(['message' => 'El slug ya está en uso por otro curso.'], 422);
                }
            }

            $course->update($request->all());

            return response()->json([
                'message' => 'Curso actualizado correctamente.',
                'data' => $course,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el curso.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar un curso
     */
    public function destroy(string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $course->delete();

            return response()->json(['message' => 'Curso eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el curso.'], 500);
        }
    }
}
