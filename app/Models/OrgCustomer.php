<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array', // Para poder manejar JSON fácilmente
    ];

    // Autogenerar el UID cuando se crea el cliente
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'cus_'.strtoupper(Str::random(16));
            }
        });
    }

    // RELACIÓN: Pertenece a una compañía (Multi-tenant)
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    // RELACIÓN: Un cliente puede tener muchas ventas/transacciones
    public function sales()
    {
        return $this->hasMany(OrgSale::class, 'org_customer_id');
    }
}
