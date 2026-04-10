<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgPaymentLinkMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'seller_id',
        'ghl_payment_link_id',
        'service_name',
        'is_active',
    ];

    // Autogenerar el UID cuando se crea el registro
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'map_'.strtoupper(Str::random(26));
            }
        });
    }

    // Relación: Un mapeo pertenece a una compañía
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    // Relación: Un mapeo pertenece a un vendedor (Usuario)
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
