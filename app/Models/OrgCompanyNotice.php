<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgCompanyNotice extends Model
{
    protected $fillable = [
        'uid',
        'org_company_id',
        'org_area_id',
        'created_by',
        'title',
        'body',
        'published_at',
        'is_active',

        'is_pinned',
        'pinned_until',
        'notice_level_id',

    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',

        'is_pinned' => 'boolean',
        'pinned_until' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function area()
    {
        return $this->belongsTo(OrgArea::class, 'org_area_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function level()
    {
        return $this->belongsTo(NoticeLevel::class, 'notice_level_id');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('org_area_id');
    }

    public function scopeForArea($query, $areaId)
    {
        return $query->where('org_area_id', $areaId);
    }
}
