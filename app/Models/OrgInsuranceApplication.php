<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; // Importación necesaria para generar cadenas aleatorias
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class OrgInsuranceApplication extends Model
{
    use HasFactory, SoftDeletes,LogsActivity;

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
        'won_at',
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
          'won_at' => 'datetime',
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
    // 3. CONFIGURACIÓN DE ACTIVITY LOG
    // ==========================================
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Registra cualquier cambio en los campos del array $fillable
            ->logOnlyDirty() // MUY IMPORTANTE: Solo guarda el log si el valor realmente cambió
            ->dontSubmitEmptyLogs() // Evita guardar logs basura si alguien le da a "Guardar" sin modificar nada
            ->useLogName('insurance_application'); // Etiqueta para filtrar fácilmente en la base de datos
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