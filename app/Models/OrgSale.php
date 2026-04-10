<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'source_type',
        'source_id',
        'customer_name',
        'customer_email',
        'product_name',
        'total_amount',
        'seller_id',
        'commission_amount',
        'commission_status',
        'processor_id',
        'processor_commission_amount',
        'processor_commission_status',
    ];

    // Autogenerar el UID cuando se crea la venta
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'sls_'.strtoupper(Str::random(26));
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processor_id');
    }
}
