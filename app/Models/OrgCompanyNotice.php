<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgCompanyNotice extends Model
{
    protected $fillable = [
        'uid',
        'org_company_id',
        'created_by',
        'title',
        'body',
        'level',
        'published_at',
        'is_active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
