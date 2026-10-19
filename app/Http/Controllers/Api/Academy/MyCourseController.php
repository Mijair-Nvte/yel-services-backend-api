<?php

namespace App\Http\Controllers\Api\Academy;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgCourse;
use App\Models\OrgCourseEnrollment;
use App\Models\OrgCourseLesson;
use App\Models\OrgCourseProgress;
use Illuminate\Http\Request;

class MyCourseController extends Controller
{
    /**
     * 📋 Listar los cursos a los que el usuario actual está inscrito con su %.
     */
    public function index(Request $request, string $companyUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = auth()->user();

            // 1. Obtenemos los IDs de los cursos donde el usuario tiene una inscripción activa
            $enrolledCourseIds = OrgCourseEnrollment::where('user_id', $user->id)
                ->where('org_company_id', $company->id)
                ->where('status', 'active')
                ->pluck('org_course_id');

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            // 2. Buscamos los cursos
            $query = OrgCourse::whereIn('id', $enrolledCourseIds)
                ->where('status', 'published') 
                ->where('is_active', true)
                ->withCount('modules') 
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('title', 'like', "%{$search}%");
            }

            $courses = $query->paginate($perPage);

            // 3. Mapeamos la respuesta y calculamos el progreso real
            $courses->getCollection()->transform(function ($course) use ($user) {
                // Contar total de lecciones del curso
                $totalLessons = OrgCourseLesson::whereHas('module', function($q) use ($course) {
                    $q->where('org_course_id', $course->id)->where('is_active', true);
                })->where('is_active', true)->count();

                // Contar lecciones completadas por el usuario en este curso
                $completedLessons = OrgCourseProgress::where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->whereHas('lesson.module', function($q) use ($course) {
                        $q->where('org_course_id', $course->id);
                    })->count();

                // Calcular porcentaje (0 a 100)
                $progressPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                return [
                    'uid' => $course->uid,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'cover_url' => $course->cover_url,
                    'modules_count' => $course->modules_count,
                    'progress_percentage' => $progressPercentage, 
                ];
            });

            return response()->json([
                'data' => $courses->items(),
                'meta' => [
                    'current_page' => $courses->currentPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                    'last_page' => $courses->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar mis cursos.'], 500);
        }
    }

    /**
     * 👁️ Ver detalle de un curso al que estoy inscrito (Para el reproductor de video)
     */
    public function show(string $companyUid, string $courseUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = auth()->user();

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->where('status', 'published')
                ->with(['modules' => function ($query) {
                    $query->where('is_active', true)->orderBy('sort_order', 'asc');
                }, 'modules.lessons' => function ($query) {
                    $query->where('is_active', true)->orderBy('sort_order', 'asc');
                }])
                ->firstOrFail();

            // Validar inscripción
            $isEnrolled = OrgCourseEnrollment::where('user_id', $user->id)
                ->where('org_course_id', $course->id)
                ->where('status', 'active')
                ->exists();

            if (!$isEnrolled) {
                return response()->json(['message' => 'No tienes acceso a este curso. Requiere inscripción.'], 403);
            }

            // Obtener los IDs de las lecciones marcadas como completadas
            $completedLessonIds = OrgCourseProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->pluck('org_course_lesson_id')
                ->toArray();

            $courseData = [
                'uid' => $course->uid,
                'title' => $course->title,
                'description' => $course->description,
                'cover_url' => $course->cover_url,
                'modules' => $course->modules->map(function ($module) use ($completedLessonIds) {
                    return [
                        'uid' => $module->uid,
                        'title' => $module->title,
                        'description' => $module->description,
                        'lessons' => $module->lessons->map(function ($lesson) use ($completedLessonIds) {
                            return [
                                'uid' => $lesson->uid,
                                'title' => $lesson->title,
                                'duration_seconds' => $lesson->duration_seconds,
                                'video_url' => $lesson->video_url,
                                'is_completed' => in_array($lesson->id, $completedLessonIds), // ✅ Lógica dinámica
                            ];
                        }),
                    ];
                }),
            ];

            return response()->json(['data' => $courseData], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Curso no encontrado o sin acceso.'], 404);
        }
    }

    /**
     * ✅ Marcar o desmarcar una lección como completada
     */
    public function toggleLessonCompletion(Request $request, string $companyUid, string $courseUid, string $lessonUid)
    {
        try {
            $company = OrgCompany::where('uid', $companyUid)->firstOrFail();
            $user = auth()->user();

            $course = OrgCourse::where('uid', $courseUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // Verificar inscripción
            $isEnrolled = OrgCourseEnrollment::where('user_id', $user->id)
                ->where('org_course_id', $course->id)
                ->where('status', 'active')
                ->exists();

            if (!$isEnrolled) {
                return response()->json(['message' => 'No tienes acceso a este curso.'], 403);
            }

            // Buscar la lección asegurando que pertenece al curso
            $lesson = OrgCourseLesson::where('uid', $lessonUid)
                ->whereHas('module', function ($query) use ($course) {
                    $query->where('org_course_id', $course->id);
                })->firstOrFail();

            // Comprobar si ya existe un registro de progreso
            $progress = OrgCourseProgress::where('user_id', $user->id)
                ->where('org_course_lesson_id', $lesson->id)
                ->first();

            if ($progress) {
                // Si existe, lo eliminamos (Desmarcar)
                $progress->delete();
                $isCompleted = false;
                $message = 'Lección desmarcada como completada.';
            } else {
                // Si no existe, creamos el registro (Marcar)
                OrgCourseProgress::create([
                    'user_id' => $user->id,
                    'org_course_lesson_id' => $lesson->id,
                    'status' => 'completed',
                    'watch_time_seconds' => $lesson->duration_seconds ?? 0, // Auto-rellena con la duración de la lección
                    'completed_at' => now(),
                ]);
                $isCompleted = true;
                $message = 'Lección marcada como completada.';
            }

            return response()->json([
                'message' => $message,
                'data' => [
                    'lesson_uid' => $lesson->uid,
                    'is_completed' => $isCompleted
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el progreso de la lección.'], 500);
        }
    }
}