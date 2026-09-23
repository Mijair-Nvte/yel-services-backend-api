<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class OrgEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'created_by',
        'title',
        'slug',
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
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'is_active' => 'boolean',
        'resources' => 'array', 
        'meta' => 'array',
    ];

    protected $appends = [
        'cover_image_url',
        'banner_image_url'
    ];

  /**
     * Generar UID y Slug único automáticamente al crear
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uid)) {
                $event->uid = Str::uuid();
            }

            // Generación de Slug único e inteligente
            if (empty($event->slug) && !empty($event->title)) {
                $slug = Str::slug($event->title);
                $originalSlug = $slug;
                $count = 1;

                // Validar si ya existe el slug en la misma empresa y agregar sufijo si es necesario
                while (static::where('org_company_id', $event->org_company_id)->where('slug', $slug)->exists()) {
                    $slug = "{$originalSlug}-{$count}";
                    $count++;
                }

                $event->slug = $slug;
            }
        });
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
}
