<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgLoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'org_loan_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'assigned_to',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'applicant_dob',
        'applicant_address',
        'applicant_state',
        'loan_type',
        'estimated_amount',
        'status',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'applicant_dob' => 'date',
        'estimated_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Boot function from Laravel to hook into model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Genera el UID automáticamente al crear el registro
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'loa_' . strtoupper(Str::random(16));
            }
        });
    }

    // ==========================================
    // Relaciones
    // ==========================================

    /**
     * Compañía a la que pertenece la solicitud
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Usuario/Afiliado que creó la solicitud
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Administrador asignado para dar seguimiento (opcional)
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    // ==========================================
    // Scopes (Opcional - Útiles para tus controladores)
    // ==========================================

    /**
     * Scope para filtrar solo los pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}