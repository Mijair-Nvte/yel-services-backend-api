<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'title',
        'portfolio_type',
        'investment_amount',
        'cash_flow_status',
        'image_path',
        'status',
        'closed_at',
        'closing_type',
    ];

    protected $casts = [
        'investment_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                // Ajusta este prefijo a la convención de tu sistema si es necesario
                $model->uid = 'prop_'.strtoupper(Str::random(25));
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    // El Partner / Inversionista dueño de la propiedad
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope para filtrar únicamente las propiedades que cerraron con YEL 
     * y que por lo tanto califican para subir de nivel.
     */
    public function scopeQualifyingForLevel($query)
    {
        return $query->where('closing_type', 'yel_internal');
    }

    /**
     * Scope para propiedades externas (solo gestión)
     */
    public function scopeExternalOnly($query)
    {
        return $query->where('closing_type', 'external');
    }
}
