<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'org_company_id',
        'created_by',
        'title',
        'description',
        'color',
        'location',
        'meeting_url',
        'external_url',
        'starts_at',
        'ends_at',
        'is_all_day',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Generar UID automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uid)) {
                $event->uid = Str::uuid();
            }
        });
    }

    /**
     * Relaciones
     */
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
