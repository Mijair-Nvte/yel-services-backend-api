<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgCompany extends Model
{
    use HasFactory;

    protected $table = 'org_companies';

    protected $fillable = [
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

    public function users()
    {
        return $this->hasMany(OrgCompanyUser::class);
    }

    public function areas()
    {
        return $this->hasMany(OrgArea::class);
    }

    public function folders()
{
    return $this->morphMany(Folder::class, 'folderable');
}

}
