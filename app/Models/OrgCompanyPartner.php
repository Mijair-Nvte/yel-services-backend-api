<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgCompanyPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_company_id',
        'user_id',
        'referral_code',
        'tax_form_type',
        'tax_form_data',
        'custom_commission_type',
        'custom_commission_value',
        'status',
    ];

    protected $casts = [
        'tax_form_data' => 'array',
        'custom_commission_value' => 'decimal:2',
    ];

    /**
     * Relación con la compañía
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con el usuario (el partner)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
