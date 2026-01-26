<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgArea extends Model
{
    use HasFactory;

    protected $table = 'org_areas';

    protected $fillable = [
        'org_company_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($area) {
            $area->uid = $area->uid ?? 'dep_'.Str::ulid();
        });
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function folders()
    {
        return $this->morphMany(Folder::class, 'folderable');
    }
}
