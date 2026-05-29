<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'status',
        'current_step',
        'progress_percentage',
    ];

    /**
     * Evento boot para generar automáticamente el UID único al crear la solicitud
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'loan_'.strtolower(Str::ulid());
            }
        });
    }

    /**
     * Relación con la compañía (Tenant)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con el usuario solicitante
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los bloques de respuestas guardados por separado
     */
    public function sections(): HasMany
    {
        return $this->hasMany(LoanApplicationSection::class, 'loan_application_id');
    }
}
