<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrgCompany extends Model
{
   use HasFactory, SoftDeletes;

    protected $table = 'org_companies';

    protected $fillable = [
        'owner_id',
        'uid',
        'name',
        'slug',
        'country',
        'state',
        'city',
        'description',
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($company) {
            $company->uid = 'wsk_'.Str::ulid();
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'org_company_users')
            ->withTimestamps();
    }

    public function areas()
    {
        return $this->hasMany(OrgArea::class);
    }

    public function folders()
    {
        return $this->morphMany(Folder::class, 'folderable');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrgCompanyInvitation::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(OrgCompanyLink::class);
    }

    public function scopeForUser(Builder $query, int $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('is_active', true);
        });
    }

    public function events()
    {
        return $this->hasMany(OrgEvent::class);
    }

    /**
     * Órdenes de servicio procesadas en la compañía
     */
    public function serviceOrders()
    {
        return $this->hasMany(OrgServiceOrder::class, 'org_company_id');
    }

    /**
     * Relación: Una empresa tiene muchas cuentas bancarias
     */
    public function bankAccounts()
    {
        return $this->hasMany(OrgBankAccount::class, 'org_company_id');
    }
}
