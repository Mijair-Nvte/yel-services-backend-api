<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'org_sale_id',
        'org_service_id',
        'org_customer_id',
        'assigned_to',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array', // Transforma el JSON de la BD en un array de PHP automáticamente
    ];

    // Generar el identificador único público sro_ al crear el registro
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'sro_' . strtoupper(Str::random(10));
            }
        });
    }

    /**
     * Relación con la compañía dueña de la orden
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con la venta origen del servicio
     */
    public function sale()
    {
        return $this->belongsTo(OrgSale::class, 'org_sale_id');
    }

    /**
     * Relación con el servicio del catálogo que se está ejecutando
     */
    public function service()
    {
        return $this->belongsTo(OrgService::class, 'org_service_id');
    }

    /**
     * Relación con el cliente final del trámite
     */
    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
    }

    /**
     * El usuario principal (Owner) asignado al seguimiento y resolución del trámite
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Los usuarios del equipo que actúan como seguidores/apoyo en esta orden (Muchos a Muchos)
     */
    public function followers()
    {
        return $this->belongsToMany(
            User::class,
            'org_service_order_followers',
            'org_service_order_id',
            'user_id'
        )->withTimestamps();
    }
}