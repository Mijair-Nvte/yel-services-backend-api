<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgModuleSetting extends Model
{
    use HasFactory;

    /**
     * Los campos que pueden ser asignados masivamente.
     */
    protected $fillable = [
        'org_company_id',
        'module_name',
        'settings',
        'is_active',
    ];

    /**
     * Los atributos que deben ser convertidos (casteados).
     * Esta es la sintaxis moderna de Laravel 11/12.
     */
    protected function casts(): array
    {
        return [
            // Convierte automáticamente el JSON de la BD a un Array de PHP y viceversa
            'settings' => 'array', 
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relación: Esta configuración pertenece a una Compañía/Tenant.
     */
    public function orgCompany(): BelongsTo
    {

        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}