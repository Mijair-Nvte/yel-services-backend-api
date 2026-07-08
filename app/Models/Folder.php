<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Folder extends Model
{
    protected $fillable = [
        'uid',
        'org_company_id',
        'name',
        'shared_platforms',
        'parent_id',
        'folderable_id',
        'folderable_type',
        'created_by',
        'order',
    ];

    protected $casts = [
        'shared_platforms' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($folder) {
            $folder->uid ??= 'fld_'.Str::ulid();
        });
    }

    public function scopeForPlatform($query, $platform)
    {
        return $query->whereJsonContains('shared_platforms', $platform);
    }
    
    // 🔗 Polimórfico
    public function folderable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}
