<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class OrgLoanApplication extends Model
{
    use HasFactory, SoftDeletes,LogsActivity;

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
        'org_customer_id',
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
        'won_at',
        'notes',
        'metadata',
        'commission_amount',   
        'commission_status',   
        'seller_payout_date',  
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
        'seller_payout_date' => 'date',
        'won_at' => 'datetime',
    ];

    // ==========================================
    // 3. CONFIGURACIÓN DE ACTIVITY LOG
    // ==========================================
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Registra cualquier cambio en los campos del array $fillable
            ->logOnlyDirty() // MUY IMPORTANTE: Solo guarda el log si el valor realmente cambió
            ->dontSubmitEmptyLogs() // Evita guardar logs basura si alguien le da a "Guardar" sin modificar nada
            ->useLogName('loan_application'); // Etiqueta para filtrar fácilmente en la base de datos
    }

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

        // 2. Controlar automáticamente la fecha 'won_at' antes de actualizar
        static::updating(function ($model) {
            // Verificamos si el campo 'status' fue modificado en esta petición
            if ($model->isDirty('status')) {
                
                if ($model->status === 'Won') {
                    // Si cambió a Won, le ponemos la fecha y hora actual
                    $model->won_at = now();
                } else {
                    // Si cambió a cualquier otra cosa (Lost, Open, Abandon), reseteamos la fecha
                    $model->won_at = null;
                }
                
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
    
    /**
     * Cliente centralizado de la solicitud
     */
    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
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