<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'description',
        'cover_image',
        'availability_type',
        'available_states',
        'stripe_product_id',
        'stripe_price_id',
        'price',
        'default_commission_type',
        'default_commission_value',
        'is_active',
        'default_assignee_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'default_commission_value' => 'decimal:2',
        'available_states' => 'array',
    ];

    protected $appends = ['cover_image_url'];

    // Autogenerar el UID cuando se crea el servicio
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'srv_'.strtoupper(Str::random(26));
            }
        });
    }

    /**
     * <-- 3. Accesor: Genera la URL pública completa de la imagen al vuelo
     */
    public function getCoverImageUrlAttribute()
    {
        if (! $this->cover_image) {
            return null; // Si no hay imagen, devuelve null
        }

        // MUY IMPORTANTE: Usamos el disco 'r2_public' donde realmente guardamos la imagen
        return Storage::disk('r2_public')->url($this->cover_image);
    }

    /**
     * Relación con la compañía
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con las ventas asociadas a este servicio
     */
    public function sales()
    {
        return $this->hasMany(OrgSale::class, 'org_service_id');
    }

    /**
     * 👇 Relación: El usuario encargado por defecto de este servicio
     */
    public function defaultAssignee()
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    /**
     * 👇 Relación: Los usuarios seguidores por defecto de este servicio (Muchos a Muchos)
     */
    public function defaultFollowers()
    {
        return $this->belongsToMany(
            User::class, 
            'org_service_default_followers', // Nombre exacto de tu nueva tabla pivote
            'org_service_id',                // Foreign key en la pivote del modelo actual
            'user_id'                        // Foreign key en la pivote del modelo relacionado
        )->withTimestamps();
    }
    

    /**
     * Órdenes activas ejecutando este servicio
     */
    public function serviceOrders()
    {
        return $this->hasMany(OrgServiceOrder::class, 'org_service_id');
    }
    
}
