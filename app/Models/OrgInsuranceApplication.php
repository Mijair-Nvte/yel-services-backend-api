<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; // Importación necesaria para generar cadenas aleatorias

class OrgInsuranceApplication extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que se pueden asignar de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'org_customer_id',
        'assigned_to',
        'insurance_type',
        'status',
        'commission_amount',  
        'commission_status',  
        'seller_payout_date', 
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'applicant_dob',
        'applicant_address',
        'applicant_state',
        'metadata',
        'notes',
    ];

    /**
     * Los atributos que deben ser casteados a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'applicant_dob' => 'date',
        'metadata' => 'array', 
        'commission_amount' => 'decimal:2',
        'seller_payout_date' => 'date',
    ];

    /**
     * Autogenerar el UID público cuando se crea la solicitud de seguro.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'ins_' . strtoupper(Str::random(16));
            }
        });
    }

    /**
     * Relación con la compañía (Tenant).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con el usuario (Cliente) que generó la solicitud.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el usuario interno o administrador asignado para el seguimiento.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Cliente centralizado de la solicitud de seguro
     */
    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
    }
}