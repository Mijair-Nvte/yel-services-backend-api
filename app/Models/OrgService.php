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
}
