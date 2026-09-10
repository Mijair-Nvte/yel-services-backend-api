<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class OrgTicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array', 
    ];

    /**
     * Generar UID automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticketType) {
            if (empty($ticketType->uid)) {
                $ticketType->uid = Str::uuid();
            }
        });
    }

    // Relación con la compañía (Tenant)
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    // Relación muchos a muchos con eventos 
 public function events()
    {
        return $this->belongsToMany(OrgEvent::class, 'org_event_ticket_types')
                    ->withPivot('capacity', 'price', 'metadata') 
                    ->withTimestamps();
    }
}