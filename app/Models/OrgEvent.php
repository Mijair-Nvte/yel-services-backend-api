<?php

namespace App\Models;

use App\Traits\SerializesLocalDates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgEvent extends Model
{
    use HasFactory, SerializesLocalDates;

    protected $fillable = [
        'uid',
        'org_company_id',
        'created_by',
        'title',
        'slug',
        'event_tag',
        'description',
        'cover_image',
        'banner_image',
        'resources',
        'meta',
        'color',
        'location',
        'meeting_url',
        'external_url',
        'target_platform',
        'starts_at',
        'ends_at',
        'is_all_day',
        'is_active',
        'is_private',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'is_active' => 'boolean',
        'is_private' => 'boolean',
        'resources' => 'array',
        'meta' => 'array',
    ];

    protected $appends = [
        'cover_image_url',
        'banner_image_url',
        'confirmation_url',
    ];

    protected $hidden = [
        'meeting_url',
        'external_url',
    ];

    /**
     * Generar UID y Slug único automáticamente al crear
     */
    /**
     * Generar UID, Slug y Event Tag automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uid)) {
                $event->uid = Str::uuid();
            }

            if (empty($event->slug) && ! empty($event->title)) {
                $event->slug = static::generateUniqueSlug(
                    $event->title,
                    $event->org_company_id,
                    (bool) $event->is_private
                );
            }

            if (empty($event->event_tag)) {
                $startsAt = ! empty($event->starts_at)
                    ? Carbon::parse($event->starts_at)->timezone('America/Chicago')
                    : now()->timezone('America/Chicago');

                $dateFormatted = $startsAt->format('Ymd');
                $shortUid = substr((string) $event->uid, 0, 6);

                $event->event_tag = "event-{$dateFormatted}-{$shortUid}";
            }
        });

        // Regenerar slug si cambia el título o la privacidad del evento
        static::updating(function ($event) {
            if (! $event->isDirty('title') && ! $event->isDirty('is_private')) {
                return;
            }

            if (empty($event->title)) {
                return;
            }

            $event->slug = static::generateUniqueSlug(
                $event->title,
                $event->org_company_id,
                (bool) $event->is_private,
                $event->id
            );
        });
    }

    /**
     * Genera un slug único por empresa.
     * - Privados: sufijo aleatorio para que el link no sea adivinable.
     * - $ignoreId: excluye al propio evento al validar (para updates).
     */
    public static function generateUniqueSlug(string $title, int $companyId, bool $isPrivate = false, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);

        if ($isPrivate) {
            $baseSlug .= '-'.Str::lower(Str::random(8));
        }

        $slug = $baseSlug;
        $count = 1;

        while (
            static::where('org_company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Relaciones
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'org_event_attendees')
            ->withTimestamps();
    }

    public function ticketTypes()
    {
        return $this->belongsToMany(OrgTicketType::class, 'org_event_ticket_types', 'org_event_id', 'org_ticket_type_id')
            ->using(OrgEventTicketType::class) // Le decimos que use nuestro modelo Pivot
            ->withPivot('id', 'capacity', 'price', 'metadata')
            ->withTimestamps();
    }

    /**
     * Registros transaccionales del evento (La tabla pivote con detalles)
     */
    public function registrations()
    {
        return $this->hasMany(OrgEventRegistration::class, 'org_event_id');
    }

    /**
     * Clientes directamente registrados al evento (Relación a través de la tabla pivote)
     */
    public function registeredCustomers()
    {
        return $this->belongsToMany(OrgCustomer::class, 'org_event_registrations', 'org_event_id', 'org_customer_id')
            ->withPivot('uid', 'registered_at', 'is_new_lead', 'attended', 'ticket_quantity', 'source', 'notes')
            ->withTimestamps();
    }

    /**
     *  Accesor para la URL pública del Cover (Portada)
     */
    public function getCoverImageUrlAttribute()
    {
        if (! $this->cover_image) {
            return null;
        }

        return Storage::disk('r2_public')->url($this->cover_image);
    }

    /**
     * Accesor para la URL pública del Banner
     */
    public function getBannerImageUrlAttribute()
    {
        if (! $this->banner_image) {
            return null;
        }

        return Storage::disk('r2_public')->url($this->banner_image);
    }

    /**
     * Accesor para transformar los recursos: transforma las rutas relativas en URLs públicas completas al serializar.
     */
    public function getResourcesAttribute($value)
    {
        $resources = json_decode($value, true);

        if (! is_array($resources)) {
            return [];
        }

        return array_map(function ($resource) {
            // Si tiene una ruta interna guardada (que no empiece por http), le armamos la URL pública
            if (! empty($resource['url']) && ! str_starts_with($resource['url'], 'http')) {
                $resource['url'] = Storage::disk('r2_public')->url($resource['url']);
            }

            return $resource;
        }, $resources);
    }

    /**
     * Accesor para generar la URL pública de Acceso / Confirmación
     */
    public function getConfirmationUrlAttribute()
    {
        // Tomamos el dominio base de tus variables de entorno (o por defecto)
        $domain = config('app.events_frontend_url', 'https://eventos.yaestoylisto.com');

        // Generamos la ruta limpia usando el slug dinámico del evento
        return "{$domain}/acceso/{$this->slug}";
    }

    /**
     * Solo eventos visibles en el listado público (excluye privados/exclusivos)
     */
    public function scopePubliclyListed($query)
    {
        return $query->where('is_private', false);
    }
}
