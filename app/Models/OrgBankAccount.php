<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgBankAccount extends Model
{
    use HasFactory;

    protected $table = 'org_bank_accounts';

    protected $fillable = [
        'uid',
        'org_company_id',
        'user_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'clabe',
    ];

    /**
     * Eventos del modelo para generar el UID automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uid)) {
                // Genera un prefijo 'oba_' (Org Bank Account) + string aleatorio
                $model->uid = 'oba_' . Str::random(24);
            }
        });
    }

    /**
     * Relación: Pertenece a una Empresa (Tenant)
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación: Pertenece a un Usuario (El que la registró)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}