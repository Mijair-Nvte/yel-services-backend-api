<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseLesson;
use App\Models\OrgCourseModule;
use App\Models\OrgCourseResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgCourseContentController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 🌳 Obtener el árbol completo: Módulos y sus Lecciones ordenadas
     */
    public function index(string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $modules = $course->modules()
                ->with([
                    'resources',
                    'lessons' => function ($query) {
                        $query->orderBy('sort_order', 'asc')->with('resources');
                    },
                ])
                ->orderBy('sort_order', 'asc')
                ->get();

            return response()->json([
                'data' => clone $modules,
                'course_resources' => $course->resources,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el contenido del curso.'], 500);
        }
    }

    /**
     * 📁 Crear un nuevo Módulo
     */
    public function storeModule(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $maxSortOrder = $course->modules()->max('sort_order') ?? 0;

            $module = OrgCourseModule::create([
                'uid' => 'mod_'.Str::random(16),
                'org_course_id' => $course->id,
                'title' => $request->title,
                'description' => $request->description,
                'sort_order' => $maxSortOrder + 1,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json(['message' => 'Módulo creado.', 'data' => $module], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el módulo.'], 500);
        }
    }

    /**
     * 🎥 Generar URL Pre-firmada para Videos de Lecciones (BUCKET PÚBLICO)
     */
    public function presignLessonVideo(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'file_name' => 'required|string',
                'mime_type' => 'required|string',
                'module_uid' => 'nullable|string',
            ]);

            //  Construimos la ruta dependiendo de si se especifica el módulo
            if (! empty($request->module_uid)) {
                $folderPath = "{$company->uid}/courses/{$courseUid}/modules/{$request->module_uid}/lessons";
            } else {
                $folderPath = "{$company->uid}/courses/{$courseUid}/lessons";
            }

            $safeFileName = time().'_'.Str::slug(pathinfo($request->file_name, PATHINFO_FILENAME)).'.'.pathinfo($request->file_name, PATHINFO_EXTENSION);
            $path = "{$folderPath}/{$safeFileName}";

            $disk = Storage::disk('r2_public');
            $client = $disk->getClient();
            $bucket = $disk->getConfig()['bucket'];

            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $path,
                'ContentType' => $request->mime_type,
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
     * 📝 Crear una nueva Lección dentro de un Módulo
     */
    public function storeLesson(Request $request, string $uid, string $courseUid, string $moduleUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $module = OrgCourseModule::where('uid', $moduleUid)->firstOrFail();

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video_path' => 'nullable|string',
                'duration_seconds' => 'nullable|integer',
                'is_free_preview' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $maxSortOrder = $module->lessons()->max('sort_order') ?? 0;

            $lesson = OrgCourseLesson::create([
                'uid' => 'lsn_'.Str::random(16),
                'org_course_module_id' => $module->id,
                'title' => $request->title,
                'description' => $request->description,
                'video_path' => $request->video_path,
                'duration_seconds' => $request->duration_seconds ?? 0,
                'is_free_preview' => $request->is_free_preview ?? false,
                'sort_order' => $maxSortOrder + 1,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json(['message' => 'Lección creada.', 'data' => $lesson], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la lección.'], 500);
        }
    }

    /**
     * ✏️ Actualizar un Módulo
     */
    public function updateModule(Request $request, string $uid, string $courseUid, string $moduleUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $module = OrgCourseModule::where('uid', $moduleUid)->firstOrFail();

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $module->update([
                'title' => $request->title,
                'description' => $request->description,
                'is_active' => $request->is_active ?? $module->is_active,
            ]);

            return response()->json(['message' => 'Módulo actualizado.', 'data' => $module], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el módulo: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🗑️ Eliminar un Módulo (recursos del módulo + lecciones, videos y recursos adjuntos en R2)
     */
    public function destroyModule(string $uid, string $courseUid, string $moduleUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            // Cargar el módulo con sus recursos y lecciones (con los recursos de cada lección)
            $module = OrgCourseModule::where('uid', $moduleUid)
                ->with(['resources', 'lessons.resources'])
                ->firstOrFail();

            // 1. Eliminar recursos adjuntos DIRECTOS del Módulo
            foreach ($module->resources as $resource) {
                if (! empty($resource->file_url)) {
                    Storage::disk('r2_public')->delete($resource->file_url);
                }
                $resource->delete();
            }

            // 2. Recorrer las lecciones del módulo
            foreach ($module->lessons as $lesson) {
                // Borrar video de la lección
                if (! empty($lesson->video_path)) {
                    Storage::disk('r2_public')->delete($lesson->video_path);
                }

                // Borrar recursos adjuntos de la lección
                foreach ($lesson->resources as $resource) {
                    if (! empty($resource->file_url)) {
                        Storage::disk('r2_public')->delete($resource->file_url);
                    }
                    $resource->delete();
                }

                $lesson->delete();
            }

            // 3. Eliminar el módulo
            $module->delete();

            return response()->json(['message' => 'Módulo y todo su contenido eliminados correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el módulo: '.$e->getMessage()], 500);
        }
    }

    /**
     * ✏️ Actualizar una Lección
     */
    public function updateLesson(Request $request, string $uid, string $courseUid, string $moduleUid, string $lessonUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $lesson = OrgCourseLesson::where('uid', $lessonUid)->firstOrFail();

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video_path' => 'nullable|string',
                'duration_seconds' => 'nullable|integer',
                'is_free_preview' => 'boolean',
                'is_active' => 'boolean',
            ]);

            // Si se subió un video nuevo y es diferente al anterior, borramos el viejo de R2
            if (! empty($request->video_path) && $request->video_path !== $lesson->video_path) {
                if (! empty($lesson->video_path)) {
                    Storage::disk('r2')->delete($lesson->video_path);
                }
            }

            $lesson->update([
                'title' => $request->title,
                'description' => $request->description,
                'video_path' => $request->video_path ?? $lesson->video_path,
                'duration_seconds' => $request->duration_seconds ?? $lesson->duration_seconds,
                'is_free_preview' => $request->is_free_preview ?? $lesson->is_free_preview,
                'is_active' => $request->is_active ?? $lesson->is_active,
            ]);

            return response()->json(['message' => 'Lección actualizada correctamente.', 'data' => $lesson], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la lección: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🗑️ Eliminar una Lección (video + recursos adjuntos en R2)
     */
    public function destroyLesson(string $uid, string $courseUid, string $moduleUid, string $lessonUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            // Cargar la lección con sus recursos adjuntos
            $lesson = OrgCourseLesson::where('uid', $lessonUid)->with('resources')->firstOrFail();

            // 1. Eliminar el video principal de la lección
            if (! empty($lesson->video_path)) {
                Storage::disk('r2_public')->delete($lesson->video_path);
            }

            // 2. Eliminar los recursos adjuntos a esta lección (archivos R2 y DB)
            foreach ($lesson->resources as $resource) {
                if (! empty($resource->file_url)) {
                    Storage::disk('r2_public')->delete($resource->file_url);
                }
                $resource->delete();
            }

            // 3. Eliminar la lección de la base de datos
            $lesson->delete();

            return response()->json(['message' => 'Lección y sus recursos eliminados correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la lección: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🔄 Reordenar Módulos
     */
    public function reorderModules(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'modules' => 'required|array',
                'modules.*.uid' => 'required|exists:org_course_modules,uid',
                'modules.*.sort_order' => 'required|integer',
            ]);

            foreach ($request->modules as $item) {
                OrgCourseModule::where('uid', $item['uid'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }

            return response()->json(['message' => 'Módulos reordenados correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al reordenar: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🔄 Reordenar Lecciones
     */
    public function reorderLessons(Request $request, string $uid, string $courseUid, string $moduleUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            // Validar que el módulo exista y pertenezca al curso
            $module = OrgCourseModule::where('uid', $moduleUid)->firstOrFail();

            $request->validate([
                'lessons' => 'required|array',
                'lessons.*.uid' => 'required|string|exists:org_course_lessons,uid',
                'lessons.*.sort_order' => 'required|integer',
            ]);

            foreach ($request->lessons as $item) {
                OrgCourseLesson::where('uid', $item['uid'])
                    ->where('org_course_module_id', $module->id)
                    ->update([
                        'sort_order' => $item['sort_order'],
                    ]);
            }

            return response()->json(['message' => 'Lecciones reordenadas correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al reordenar lecciones: '.$e->getMessage()], 500);
        }
    }

    /**
     * ☁️ Generar URL Pre-firmada Estrictamente Organizada para Recursos
     */
    public function presignResource(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'file_name' => 'required|string',
                'mime_type' => 'required|string',
                'resourceable_type' => 'required|in:course,module,lesson',
                'resourceable_uid' => 'required|string',
            ]);

            $type = $request->resourceable_type;
            $resUid = $request->resourceable_uid;

            $basePath = "{$company->uid}/courses/{$courseUid}";
            $folderPath = '';

            // 🏗️ Construimos la ruta estricta dependiendo de dónde se va a guardar
            if ($type === 'course') {
                $folderPath = "{$basePath}/resources";

            } elseif ($type === 'module') {
                $folderPath = "{$basePath}/modules/{$resUid}/resources";

            } elseif ($type === 'lesson') {
                // Buscamos la lección para saber a qué módulo pertenece y mantener la ruta intacta
                $lesson = OrgCourseLesson::where('uid', $resUid)->firstOrFail();
                $module = OrgCourseModule::findOrFail($lesson->org_course_module_id);
                $folderPath = "{$basePath}/modules/{$module->uid}/lessons/{$resUid}/resources";
            }

            // Limpiamos el nombre del archivo y armamos la ruta final
            $safeFileName = time().'_'.Str::slug(pathinfo($request->file_name, PATHINFO_FILENAME)).'.'.pathinfo($request->file_name, PATHINFO_EXTENSION);
            $path = "{$folderPath}/{$safeFileName}";

            $disk = Storage::disk('r2_public');
            $client = $disk->getClient();
            $bucket = $disk->getConfig()['bucket'];

            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $path,
                'ContentType' => $request->mime_type,
            ]);

            $signedRequest = $client->createPresignedRequest($command, '+15 minutes');
            $uploadUrl = (string) $signedRequest->getUri();
            $publicUrl = $disk->url($path);

            return response()->json([
                'upload_url' => $uploadUrl,
                'path' => $path, // Esta es la ruta que tu frontend guardará y mandará a storeResource
                'public_url' => $publicUrl,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar la pre-firma: '.$e->getMessage()], 500);
        }
    }

    /**
     * 📎 Guardar el recurso en la base de datos (Polimórfico)
     */
    public function storeResource(Request $request, string $uid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $request->validate([
                'resourceable_type' => 'required|in:course,module,lesson',
                'resourceable_uid' => 'required|string',
                'title' => 'required|string|max:255',
                'file_name' => 'required|string|max:255',
                'file_url' => 'required|string', // La ruta relativa ($path) que devolvió presignResource
                'mime_type' => 'nullable|string',
                'file_size' => 'nullable|integer',
            ]);

            // Encontramos al padre real en la base de datos
            switch ($request->resourceable_type) {
                case 'course':
                    $parent = OrgCourse::where('uid', $request->resourceable_uid)
                        ->where('org_company_id', $company->id)->firstOrFail();
                    break;
                case 'module':
                    $parent = OrgCourseModule::where('uid', $request->resourceable_uid)->firstOrFail();
                    break;
                case 'lesson':
                    $parent = OrgCourseLesson::where('uid', $request->resourceable_uid)->firstOrFail();
                    break;
            }

            $maxSortOrder = $parent->resources()->max('sort_order') ?? 0;

            // Al usar parent->resources()->create(), Laravel llena automáticamente
            // el resourceable_type y resourceable_id
            $resource = $parent->resources()->create([
                'org_company_id' => $company->id,
                'title' => $request->title,
                'file_name' => $request->file_name,
                'file_url' => $request->file_url,
                'mime_type' => $request->mime_type,
                'file_size' => $request->file_size,
                'sort_order' => $maxSortOrder + 1,
                'is_active' => true,
            ]);

            return response()->json(['message' => 'Recurso adjuntado.', 'data' => $resource], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al adjuntar el recurso: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🗑️ Eliminar un Recurso Adjunto (y su archivo físico en R2)
     */
    public function destroyResource(string $uid, string $courseUid, string $resourceUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            // Asegúrate de importar el modelo OrgCourseResource al inicio del archivo si no lo tienes:
            //
            $resource = \App\Models\OrgCourseResource::where('uid', $resourceUid)->firstOrFail();

            // Borramos el archivo del bucket público
            if (! empty($resource->file_url)) {
                Storage::disk('r2_public')->delete($resource->file_url);
            }

            // Eliminamos el registro de la base de datos
            $resource->delete();

            return response()->json(['message' => 'Recurso eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el recurso: '.$e->getMessage()], 500);
        }
    }
}
