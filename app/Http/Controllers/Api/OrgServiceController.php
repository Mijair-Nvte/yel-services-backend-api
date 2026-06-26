<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrgServiceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspace;

    /**
     * 📋 Listar todos los servicios de la compañía
     */
    public function index(string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            // 🛡️ Seguridad Contextual
            $this->authorizeWorkspace($company);
            $this->authorize('view_services');

            $services = OrgService::where('org_company_id', $company->id)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json(['data' => $services], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al listar los servicios.'], 500);
        }
    }

    /**
     * ➕ Crear un nuevo servicio
     */
    public function store(Request $request, string $uid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_services');

            \Log::info('Datos recibidos:', $request->all());

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'availability_type' => 'required|string|in:all,restricted', // Validación nueva
                'available_states' => 'nullable|array', // Debe ser array
                'available_states.*' => 'string|size:2', // Ej: "TX", "FL"
                'stripe_product_id' => 'required|string|max:255',
                'stripe_price_id' => 'required|string|max:255',
                'price' => 'nullable|numeric|min:0',
                'default_commission_type' => 'required|string|in:percentage,fixed',
                'default_commission_value' => 'required|numeric|min:0',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'is_active' => 'boolean',
            ]);

            // Validar unicidad del Price ID de Stripe dentro de la misma empresa para evitar duplicados
            $exists = OrgService::where('org_company_id', $company->id)
                ->where('stripe_price_id', $request->stripe_price_id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Este Stripe Price ID ya está registrado en otro servicio.'], 422);
            }

            // Forzar a null si el tipo es 'all' para no tener basura en la BD
            $availableStates = $request->availability_type === 'all' ? null : $request->available_states;

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');

                // 1. Obtenemos el nombre original sin la extensión
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                // 2. Limpiamos el nombre original (quita espacios, emojis, acentos)
                $cleanName = \Str::slug($originalName);

                // 3. Obtenemos la extensión (jpg, png, webp)
                $extension = $file->getClientOriginalExtension();

                // 4. Armamos nuestro nombre organizado: nombre-limpio-timestamp.extension
                // Ej: mi-portada-genial-17189382.webp
                $fileName = $cleanName.'-'.time().'.'.$extension;

                // 5. Usamos storeAs para decirle a Laravel EXACTAMENTE cómo llamarlo
                $coverImagePath = $file->storeAs('services_covers', $fileName, 'r2_public');
            }

            $service = OrgService::create([
                'org_company_id' => $company->id,
                'name' => $request->name,
                'description' => $request->description,
                'cover_image' => $coverImagePath,
                'availability_type' => $request->availability_type,
                'available_states' => $availableStates,
                'stripe_product_id' => $request->stripe_product_id,
                'stripe_price_id' => $request->stripe_price_id,
                'price' => $request->price,
                'default_commission_type' => $request->default_commission_type,
                'default_commission_value' => $request->default_commission_value,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'message' => 'Servicio creado correctamente.',
                'data' => $service,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el servicio.'], 500);
        }
    }

    /**
     * ✏️ Actualizar un servicio existente
     */
    public function update(Request $request, string $uid, string $serviceUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_services');

            $service = OrgService::where('uid', $serviceUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'availability_type' => 'sometimes|required|string|in:all,restricted',
                'available_states' => 'nullable|array',
                'available_states.*' => 'string|size:2',
                'stripe_product_id' => 'sometimes|required|string|max:255',
                'stripe_price_id' => 'sometimes|required|string|max:255',
                'price' => 'sometimes|nullable|numeric|min:0',
                'default_commission_type' => 'sometimes|required|string|in:percentage,fixed',
                'default_commission_value' => 'sometimes|required|numeric|min:0',
                'is_active' => 'boolean',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            if ($request->has('stripe_price_id') && $request->stripe_price_id !== $service->stripe_price_id) {
                $exists = OrgService::where('org_company_id', $company->id)
                    ->where('stripe_price_id', $request->stripe_price_id)
                    ->exists();

                if ($exists) {
                    return response()->json(['message' => 'Este Stripe Price ID ya está registrado en otro servicio.'], 422);
                }
            }

            $updateData = $request->all();

            // Limpiar los estados si se cambia a 'all'
            if ($request->has('availability_type') && $request->availability_type === 'all') {
                $updateData['available_states'] = null;
            }

            if ($request->hasFile('cover_image')) {
                // Borramos la imagen anterior de Cloudflare R2
                if ($service->cover_image) {
                    Storage::disk('r2_public')->delete($service->cover_image);
                }

                $file = $request->file('cover_image');

                // Armamos el nuevo nombre organizado
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $cleanName = \Str::slug($originalName);
                $extension = $file->getClientOriginalExtension();

                // Aquí puedes incluso usar el UID del servicio para que sea aún más organizado
                // Ej: service-abc123xyz-17189382.webp
                $fileName = 'service-'.$service->uid.'-'.time().'.'.$extension;

                // Guardamos con el nuevo nombre
                $updateData['cover_image'] = $file->storeAs('services_covers', $fileName, 'r2_public');
            }

            $service->update($updateData);

            return response()->json([
                'message' => 'Servicio actualizado correctamente.',
                'data' => $service,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el servicio.'], 500);
        }
    }

    /**
     * 🗑️ Eliminar (Soft Delete) un servicio
     */
    public function destroy(string $uid, string $serviceUid)
    {
        try {
            $company = OrgCompany::where('uid', $uid)->firstOrFail();

            $this->authorizeWorkspace($company);
            $this->authorize('manage_services');

            $service = OrgService::where('uid', $serviceUid)
                ->where('org_company_id', $company->id)
                ->firstOrFail();

            $service->delete(); // Gracias a SoftDeletes en tu modelo, esto no lo borra físicamente

            return response()->json(['message' => 'Servicio eliminado correctamente.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el servicio.'], 500);
        }
    }
}
