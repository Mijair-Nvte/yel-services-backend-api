<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrgCompanyLink extends Model
{
    use HasFactory;

    protected $table = 'org_company_links';

    protected $fillable = [
        'uid',
        'org_company_id',
        'title',
        'url',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($link) {
            $link->uid = 'lnk_' . Str::ulid();
        });
    }

    // Relación: Link pertenece a una compañía
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}
