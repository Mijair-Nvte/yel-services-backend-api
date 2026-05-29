<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'description',
        'stripe_product_id',
        'stripe_price_id',
        'default_commission_type',
        'default_commission_value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_commission_value' => 'decimal:2',
    ];

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
