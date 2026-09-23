<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgEventRegistration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uid', 'org_company_id', 'org_event_id', 'org_customer_id',
        'registered_at', 'is_new_lead', 'attended', 'ticket_quantity',
        'source', 'notes'
    ];

    protected $casts = [
        'is_new_lead' => 'boolean',
        'attended' => 'boolean',
        'registered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'evr_' . strtoupper(Str::random(16));
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
    
    public function event()
    {
        return $this->belongsTo(OrgEvent::class, 'org_event_id');
    }

    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
    }
}