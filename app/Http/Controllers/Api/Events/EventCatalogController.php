<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\OrgCompany;
use App\Models\OrgEvent;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EventCatalogController extends Controller
{
    /**
     * Lista el catálogo de eventos disponibles.
     */
    public function index($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $events = OrgEvent::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($event) {
                $isPast = Carbon::parse($event->starts_at)->isPast();
                
                return [
                    'uid' => $event->uid,
                    'slug' => $event->slug, 
                    'title' => $event->title,
                    'description' => $event->description,
                   'cover_image_url' => $event->cover_image_url,  
                    'banner_image_url' => $event->banner_image_url,
                    'starts_at' => $event->starts_at,
                    'ends_at' => $event->ends_at,
                    'is_all_day' => (bool) $event->is_all_day,
                    'location' => $event->location ?: ($event->meeting_url ? 'En línea' : 'Por definir'),
                    'status' => $isPast ? 'Finalizado' : 'Próximo',
                    'resources' => $event->resources ?? [],        
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Muestra la vista detallada de un evento buscando por su slug.
     */
    public function show($companyUid, $slug)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        $event = OrgEvent::where('org_company_id', $company->id)
            ->where('slug', $slug) // <--- Búsqueda optimizada por slug para SEO
            ->where('is_active', true)
            ->firstOrFail();

        $isPast = Carbon::parse($event->starts_at)->isPast();

        return response()->json([
            'success' => true,
            'data' => [
                'uid' => $event->uid,
                'slug' => $event->slug,
                'title' => $event->title,
                'description' => $event->description,
                'cover_image_url' => $event->cover_image_url,
                'banner_image_url' => $event->banner_image_url,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'is_all_day' => (bool) $event->is_all_day,
                'location' => $event->location ?: ($event->meeting_url ? 'En línea' : 'Por definir'),
                'status' => $isPast ? 'Finalizado' : 'Próximo',
                'resources' => $event->resources ?? [],
                'meta' => $event->meta ?? [],
                'organizer' => [
                    'name' => 'Equipo de Ya Estoy Listo',
                    'website' => 'https://yaestoylisto.com'
                ]
            ]
        ]);
    }
}