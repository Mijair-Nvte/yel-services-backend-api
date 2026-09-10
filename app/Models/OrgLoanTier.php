<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrgLoanTier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'min_monthly_volume',
        'max_monthly_volume',
        'small_loan_max_amount',
        'large_loan_min_amount',
        'small_loan_fixed_fee',
        'medium_loan_percentage',
        'large_loan_cap',
        'features',
        'color_theme',
        'is_active',
    ];

    protected $casts = [
        'min_monthly_volume' => 'decimal:2',
        'max_monthly_volume' => 'decimal:2',
        'small_loan_max_amount' => 'decimal:2',
        'large_loan_min_amount' => 'decimal:2',
        'small_loan_fixed_fee' => 'decimal:2',
        'medium_loan_percentage' => 'decimal:4',
        'large_loan_cap' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'ltier_' . strtoupper(Str::random(25));
            }
        });
    }

    /**
     * Relación con la compañía (Multitenancy)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}