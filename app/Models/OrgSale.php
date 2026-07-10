<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgSale extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'org_customer_id',
        'source_type',
        'source_id',
        
        'customer_origin',
        'product_name',
        'org_service_id',
        'total_amount',
        'payment_status',
        'seller_id',
       'referral_code',
        'commission_amount',
        'commission_status',
        'seller_payout_date',
        'processor_id',
        'processor_commission_amount',
        'processor_commission_status',
        'processor_payout_date',
    ];

    protected $casts = [
        'seller_payout_date' => 'date',
        'processor_payout_date' => 'date',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'processor_commission_amount' => 'decimal:2',
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

    public function service()
    {
        return $this->belongsTo(OrgService::class, 'org_service_id');
    }


    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
    }
    
    /**
     * Orden de servicio generada por esta venta
     */
    public function serviceOrder()
    {
        return $this->hasOne(OrgServiceOrder::class, 'org_sale_id');
    }
    
}
