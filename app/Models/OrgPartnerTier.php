<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgPartnerTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid', 'org_company_id', 'name',
        'min_sales_volume', 'max_sales_volume',
        'commission_percentage', 'features', 'color_theme', 'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'min_sales_volume' => 'decimal:2',
        'max_sales_volume' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'ptier_'.strtoupper(Str::random(25));
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }


    /**
     * Relación: Un nivel tiene muchos Partners/Vendedores
     */
    public function partners()
    {
        return $this->hasMany(OrgCompanyPartner::class, 'org_partner_tier_id');
    }
}
