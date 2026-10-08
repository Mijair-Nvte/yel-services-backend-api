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
     * Lista el catálogo de los próximos 5 eventos disponibles.
     */
    public function index($companyUid)
    {
        $company = OrgCompany::where('uid', $companyUid)->firstOrFail();

        // 1. Filtramos solo los eventos futuros (partiendo de ahora), ordenados del más cercano al más lejano, limitando a 5
        $events = OrgEvent::where('org_company_id', $company->id)
            ->where('is_active', true)
            ->where('starts_at', '>=', now()) // Solo eventos vigentes/futuros
            ->orderBy('starts_at', 'asc')     // El más próximo primero
            ->take(5)                         // Máximo 5 registros
            ->get()
            ->map(function ($event) {
                return [
                    'uid' => $event->uid,
                    'slug' => $event->slug, 
                    'title' => $event->title,
                    'description' => $event->description,
                    'cover_image_url' => $event->cover_image_url,  
                    'banner_image_url' => $event->banner_image_url,
                    // Devolvemos la fecha limpia gracias al Trait del modelo (sin Z extraña)
              'starts_at' => $event->starts_at ? Carbon::parse($event->starts_at)->format('Y-m-d\TH:i:s') : null,
                    'ends_at' => $event->ends_at ? Carbon::parse($event->ends_at)->format('Y-m-d\TH:i:s') : null,

                    'is_all_day' => (bool) $event->is_all_day,
                    'location' => $event->location ?: ($event->meeting_url ? 'En línea' : 'Por definir'),
                    'status' => 'Próximo', // Como filtramos por >= now(), todos son próximos
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
            ->where('slug', $slug) 
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
          'starts_at' => $event->starts_at ? Carbon::parse($event->starts_at)->format('Y-m-d\TH:i:s') : null,
                'ends_at' => $event->ends_at ? Carbon::parse($event->ends_at)->format('Y-m-d\TH:i:s') : null,
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