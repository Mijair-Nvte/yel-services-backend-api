<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseLesson;
use App\Models\OrgCourseModule;
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
                ->with(['lessons' => function ($query) {
                    $query->orderBy('sort_order', 'asc');
                }])
                ->orderBy('sort_order', 'asc')
                ->get();

            return response()->json(['data' => $modules], 200);

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
            ]);

            // Ruta limpia dentro del workspace para el BUCKET PÚBLICO
            $path = "{$company->uid}/courses/{$courseUid}/lessons/".time().'_'.Str::slug(pathinfo($request->file_name, PATHINFO_FILENAME)).'.'.pathinfo($request->file_name, PATHINFO_EXTENSION);

            // Cambiamos a 'r2_public' para que sea accesible de forma directa y permanente
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
     * 🗑️ Eliminar un Módulo (y sus archivos físicos en R2)
     */
    public function destroyModule(string $uid, string $courseUid, string $moduleUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $module = OrgCourseModule::where('uid', $moduleUid)->with('lessons')->firstOrFail();

            // 1. Recorrer las lecciones del módulo para eliminar los videos físicos de R2
            foreach ($module->lessons as $lesson) {
                if (! empty($lesson->video_path)) {
                    // Borramos el archivo del bucket privado
                    Storage::disk('r2')->delete($lesson->video_path);
                }
                // 2. Eliminamos el registro de la lección
                $lesson->delete();
            }

            // 3. Finalmente eliminamos el módulo
            $module->delete();

            return response()->json(['message' => 'Módulo y su contenido eliminados correctamente.'], 200);

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
     * 🗑️ Eliminar una Lección (y su archivo de video físico en R2)
     */
    public function destroyLesson(string $uid, string $courseUid, string $moduleUid, string $lessonUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_courses');

            $lesson = OrgCourseLesson::where('uid', $lessonUid)->firstOrFail();

            if (! empty($lesson->video_path)) {
                Storage::disk('r2')->delete($lesson->video_path);
            }

            $lesson->delete();

            return response()->json(['message' => 'Lección eliminada correctamente.'], 200);

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
}
