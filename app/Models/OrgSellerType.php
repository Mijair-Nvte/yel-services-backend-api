<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgSellerType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'org_seller_types';

    protected $fillable = [
        'org_company_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con la Compañía (Tenant)
     */
    public function company(): BelongsTo
    {
        // Ajusta "OrgCompany::class" al nombre real de tu modelo de compañía
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con los Niveles (Tiers)
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(OrgPartnerTier::class, 'org_seller_type_id');
    }

    /**
     * Relación con los Vendedores (Partners)
     */
    public function partners(): HasMany
    {
        return $this->hasMany(OrgCompanyPartner::class, 'org_seller_type_id');
    }
    
}