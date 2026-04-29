<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrgTimeTracking extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'org_time_trackings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'status',
        'ip_address',
        'user_agent',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uid)) {
                // Genera el UID con prefijo 'ttk_' (Time Tracking) y un ULID
                $model->uid = 'ttk_' . strtoupper(Str::ulid());
            }
        });
    }

    /**
     * Get the company that owns the time tracking record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Get the user that owns the time tracking record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Helper para verificar si el tracking sigue activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && is_null($this->ended_at);
    }
}