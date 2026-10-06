<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgEventController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📅 Listar eventos por rango (conteo de registros incluido)
     */
    public function index(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_calendar');

            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $events = OrgEvent::where('org_company_id', $company->id)
                ->where(function ($query) use ($from, $to) {
                    $query->whereBetween('starts_at', [$from, $to])
                        ->orWhereBetween('ends_at', [$from, $to])
                        ->orWhere(function ($q) use ($from, $to) {
                            $q->where('starts_at', '<=', $from)
                                ->where('ends_at', '>=', $to);
                        });
                })
                ->withCount('registrations') // Total de registros
                ->withCount(['registrations as attended_count' => function ($query) {
                    $query->where('attended', true);
                }]) // Total de los que asistieron
                ->withCount(['registrations as unattended_count' => function ($query) {
                    $query->where('attended', false);
                }]) // Total de los que no asistieron
                ->orderBy('created_at', 'desc')
                ->get();

            $events->makeVisible(['meeting_url', 'external_url']);

            return response()->json($events);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar eventos.'], 500);
        }
    }

    /**
     * 📝 Crear evento (CON LOGS DE DEPURACIÓN)
     */
    public function store(Request $request, string $uid)
    {
        try {

            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $eventDataRaw = $request->input('event_data');
            \Log::info('3. RAW event_data recibido:', [$eventDataRaw]);

            $eventData = json_decode($eventDataRaw, true);

            if (! $eventData) {
                \Log::error('❌ event_data no es un JSON válido o llegó vacío.');

                return response()->json(['message' => 'Estructura de datos inválida.'], 400);
            }

            $event = OrgEvent::create([
                'org_company_id' => $company->id,
                'created_by' => Auth::id(),
                'title' => $eventData['title'],
                'description' => $eventData['description'] ?? null,
                'color' => $eventData['color'] ?? 'blue',
                'location' => $eventData['location'] ?? null,
                'meeting_url' => $eventData['meeting_url'] ?? null,
                'external_url' => $eventData['external_url'] ?? null,
                'target_platform' => $eventData['target_platform'] ?? 'yel_services',
                'starts_at' => $eventData['starts_at'],
                'ends_at' => $eventData['ends_at'] ?? null,
                'is_all_day' => $eventData['is_all_day'] ?? false,
            ]);

            $basePath = "{$company->uid}/events/{$event->uid}";

            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $fileName = 'cover-'.time().'.'.$file->getClientOriginalExtension();
                $event->cover_image = $file->storeAs("{$basePath}/covers", $fileName, 'r2_public');
            }

            if ($request->hasFile('banner_image')) {
                $file = $request->file('banner_image');
                $fileName = 'banner-'.time().'.'.$file->getClientOriginalExtension();
                $event->banner_image = $file->storeAs("{$basePath}/banners", $fileName, 'r2_public');
            }

            // PROCESAMIENTO DE RECURSOS
            $formattedResources = [];
            $resourcesList = $eventData['resources'] ?? [];

            foreach ($resourcesList as $index => $res) {
                $title = $res['title'] ?? 'Documento sin título';
                $fileUrl = $res['url'] ?? null;
                $fileInputName = "resource_file_{$index}";

                if ($request->hasFile($fileInputName)) {
                    \Log::info("✅ Archivo {$fileInputName} ENCONTRADO en la petición.");
                    $resourceFile = $request->file($fileInputName);

                    // Validar si PHP rechazó el archivo (ej: por pesar mucho en php.ini)
                    if (! $resourceFile->isValid()) {
                        \Log::error("❌ Archivo {$fileInputName} corrupto o excede límite. Mensaje: ".$resourceFile->getErrorMessage());
                    } else {
                        $originalName = pathinfo($resourceFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $cleanName = Str::slug($originalName);
                        $extension = $resourceFile->getClientOriginalExtension();
                        $resourceFileName = time()."-{$cleanName}.{$extension}";

                        try {
                            $path = $resourceFile->storeAs("{$basePath}/resources", $resourceFileName, 'r2_public');
                            $fileUrl = Storage::disk('r2_public')->url($path);
                            \Log::info("✅ Archivo subido con éxito a R2: {$fileUrl}");
                        } catch (\Exception $uploadEx) {
                            \Log::error('❌ ERROR CRÍTICO al subir a R2: '.$uploadEx->getMessage());
                        }
                    }
                } else {
                    \Log::warning("⚠️ Archivo {$fileInputName} NO LLEGÓ a Laravel (hasFile devolvió false).");
                }

                if (! empty($fileUrl)) {
                    $formattedResources[] = [
                        'title' => $title,
                        'url' => $fileUrl,
                        'type' => pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'link',
                    ];
                    \Log::info('✅ Recurso añadido a la lista final.');
                } else {
                    \Log::warning("⚠️ Recurso #{$index} DESCARTADO porque quedó sin URL.");
                }
            }

            $event->resources = $formattedResources;
            $event->save();

            $event->makeVisible(['meeting_url', 'external_url']);

            return response()->json($event, 201);

        } catch (\Exception $e) {
            \Log::error('🔥 FATAL ERROR: '.$e->getMessage().' en línea '.$e->getLine());

            return response()->json(['message' => 'Error al crear evento: '.$e->getMessage()], 500);
        }
    }

    /**
     * 🔍 Mostrar detalle (Normalizado)
     */
    public function show(string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $event->makeVisible(['meeting_url', 'external_url']);

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }
    }

    /**
     * ✏️ Actualizar evento (CON SOPORTE PARA JSON Y RECURSOS EN R2)
     */
    public function update(Request $request, string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // 1. Decodificamos el JSON enviado desde el frontend
            $eventDataRaw = $request->input('event_data');

            if (! $eventDataRaw) {
                return response()->json(['message' => 'Estructura de datos inválida.'], 400);
            }

            $eventData = json_decode($eventDataRaw, true);

            if (! $eventData) {
                return response()->json(['message' => 'Estructura JSON inválida.'], 400);
            }

            // 2. Preparamos los datos básicos a actualizar
            $updateData = [
                'title' => $eventData['title'],
                'description' => $eventData['description'] ?? null,
                'color' => $eventData['color'] ?? 'blue',
                'location' => $eventData['location'] ?? null,
                'meeting_url' => $eventData['meeting_url'] ?? null,
                'external_url' => $eventData['external_url'] ?? null,
                'target_platform' => $eventData['target_platform'] ?? 'yel_services',
                'starts_at' => $eventData['starts_at'],
                'ends_at' => $eventData['ends_at'] ?? null,
                'is_all_day' => $eventData['is_all_day'] ?? false,
            ];

            // Ruta base estandarizada
            $basePath = "{$company->uid}/events/{$event->uid}";

            // 3. Manejo de imagen de portada (Cover)
            if ($request->hasFile('cover_image')) {
                if ($event->cover_image) {
                    Storage::disk('r2_public')->delete($event->cover_image);
                }
                $file = $request->file('cover_image');
                $fileName = 'cover-'.time().'.'.$file->getClientOriginalExtension();
                $updateData['cover_image'] = $file->storeAs("{$basePath}/covers", $fileName, 'r2_public');
            }

            // 4. Manejo de imagen de banner
            if ($request->hasFile('banner_image')) {
                if ($event->banner_image) {
                    Storage::disk('r2_public')->delete($event->banner_image);
                }
                $file = $request->file('banner_image');
                $fileName = 'banner-'.time().'.'.$file->getClientOriginalExtension();
                $updateData['banner_image'] = $file->storeAs("{$basePath}/banners", $fileName, 'r2_public');
            }

            // 5. Procesamiento de Recursos y Archivos Planos Adjuntos
            $formattedResources = [];
            $resourcesList = $eventData['resources'] ?? [];

            foreach ($resourcesList as $index => $res) {
                $title = $res['title'] ?? 'Documento sin título';
                $fileUrl = $res['url'] ?? null; // Puede ser una URL externa o una URL previa ya existente
                $fileInputName = "resource_file_{$index}";

                // Si el usuario adjuntó un archivo nuevo en este índice, lo subimos a R2
                if ($request->hasFile($fileInputName)) {
                    $resourceFile = $request->file($fileInputName);

                    if ($resourceFile->isValid()) {
                        $originalName = pathinfo($resourceFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $cleanName = Str::slug($originalName);
                        $extension = $resourceFile->getClientOriginalExtension();
                        $resourceFileName = time()."-{$cleanName}.{$extension}";

                        $path = $resourceFile->storeAs("{$basePath}/resources", $resourceFileName, 'r2_public');
                        $fileUrl = Storage::disk('r2_public')->url($path);
                    }
                }

                // Si tenemos una URL (bien porque ya existía, porque se escribió una externa o porque se subió archivo nuevo)
                if (! empty($fileUrl)) {
                    $formattedResources[] = [
                        'title' => $title,
                        'url' => $fileUrl,
                        'type' => pathinfo($fileUrl, PATHINFO_EXTENSION) ?: 'link',
                    ];
                }
            }

            $updateData['resources'] = $formattedResources;

            // 6. Actualizamos el registro en la BD
            $event->update($updateData);

            $event->makeVisible(['meeting_url', 'external_url']);

            return response()->json($event);

        } catch (\Exception $e) {
            \Log::error('🔥 ERROR AL ACTUALIZAR EVENTO: '.$e->getMessage().' en línea '.$e->getLine());

            return response()->json(['message' => 'Error al actualizar evento: '.$e->getMessage()], 500);
        }
    }

    /**
     * 👥 Listar los registros / asistentes de un evento específico para el panel admin
     */
    public function registrations(string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('view_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // Obtenemos los registros con la información del cliente vinculado
            $registrations = $event->registrations()
                ->with('customer')
                ->latest()
                ->get()
                ->map(function ($reg) {
                    return [
                        'id' => $reg->id,
                        'uid' => $reg->uid,
                        // Mapeamos el estatus lógico según tu tabla
                        'status' => $reg->attended ? 'attended' : 'registered',
                        'created_at' => $reg->created_at,
                        'source' => $reg->source ?? 'Web Organica',
                        'customer' => $reg->customer ? [
                            'id' => $reg->customer->id,
                            'uid' => $reg->customer->uid,
                            'first_name' => $reg->customer->first_name,
                            'last_name' => $reg->customer->last_name,
                            'email' => $reg->customer->email,
                            'phone' => $reg->customer->phone,
                            'ghl_contact_id' => $reg->customer->contact_id ?? null, // Mapeado con tu columna contact_id de GHL
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $registrations,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar los asistentes.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar (Normalizado)
     */
    public function destroy(string $uid, string $eventUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $event = OrgEvent::where('uid', $eventUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            // Esto borra toda la carpeta del evento y su contenido (covers y banners)
            Storage::disk('r2_public')->deleteDirectory("{$company->uid}/events/{$event->uid}");

            $event->delete();

            return response()->json(['message' => 'Evento eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar evento.'], 500);
        }
    }
}
