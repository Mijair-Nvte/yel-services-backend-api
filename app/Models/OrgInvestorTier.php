<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgInvestorTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'min_properties',
        'max_properties',
        'discount_percentage',
        'features',
        'color_theme',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                // Ajusta este prefijo a la convención de tu sistema si es necesario
                $model->uid = 'tier_'.strtoupper(Str::random(25));
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}
