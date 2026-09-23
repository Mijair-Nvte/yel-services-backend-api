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
                ->orderBy('starts_at')
                ->get();

            return response()->json($events);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar eventos.'], 500);
        }
    }

    /**
     * 📝 Crear evento
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();
            $this->authorizeWorkspace($company);
            $this->authorize('manage_calendar');

            $data = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'color' => 'nullable|string|in:blue,red,green,yellow,purple,orange,pink',
                'location' => 'nullable|string|max:255',
                'meeting_url' => 'nullable|url|max:255',
                'external_url' => 'nullable|url|max:255',
                'target_platform' => 'required|string|in:yel_services,yel_pro,yel_investor',
                'starts_at' => 'required|date',
                'ends_at' => 'nullable|date|after_or_equal:starts_at',
                'is_all_day' => 'boolean',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            // 1. Extraemos las imágenes del array de datos para no guardarlas vacías
            $eventData = collect($data)->except(['cover_image', 'banner_image'])->toArray();

            // 2. Creamos el evento PRIMERO para que Laravel/BD genere el $event->uid
            $event = OrgEvent::create([
                ...$eventData,
                'org_company_id' => $company->id,
                'created_by' => Auth::id(),
                'color' => $data['color'] ?? 'blue',
            ]);

            // 3. Definimos la ruta base organizada: wsk_XXX/events/evt_YYY
            $basePath = "{$company->uid}/events/{$event->uid}";
            $needsUpdate = false;

            // 4. Subimos las imágenes a su carpeta correspondiente
            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $fileName = 'cover-'.time().'.'.$file->getClientOriginalExtension();
                $event->cover_image = $file->storeAs("{$basePath}/covers", $fileName, 'r2_public');
                $needsUpdate = true;
            }

            if ($request->hasFile('banner_image')) {
                $file = $request->file('banner_image');
                $fileName = 'banner-'.time().'.'.$file->getClientOriginalExtension();
                $event->banner_image = $file->storeAs("{$basePath}/banners", $fileName, 'r2_public');
                $needsUpdate = true;
            }

            // 5. Si subimos imágenes, actualizamos el registro
            if ($needsUpdate) {
                $event->save();
            }

            return response()->json($event, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear evento.'], 500);
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

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }
    }

    /**
     * ✏️ Actualizar (Normalizado)
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

            $data = $request->validate([
                // ... (mantén tus validaciones de update tal cual las tienes)
                'title' => 'sometimes|string|max:255',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            // Ruta base estandarizada
            $basePath = "{$company->uid}/events/{$event->uid}";

            if ($request->hasFile('cover_image')) {
                if ($event->cover_image) {
                    Storage::disk('r2_public')->delete($event->cover_image);
                }
                $file = $request->file('cover_image');
                $fileName = 'cover-'.time().'.'.$file->getClientOriginalExtension();
                $data['cover_image'] = $file->storeAs("{$basePath}/covers", $fileName, 'r2_public');
            }

            if ($request->hasFile('banner_image')) {
                if ($event->banner_image) {
                    Storage::disk('r2_public')->delete($event->banner_image);
                }
                $file = $request->file('banner_image');
                $fileName = 'banner-'.time().'.'.$file->getClientOriginalExtension();
                $data['banner_image'] = $file->storeAs("{$basePath}/banners", $fileName, 'r2_public');
            }

            $event->update($data);

            return response()->json($event);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar evento.'], 500);
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
