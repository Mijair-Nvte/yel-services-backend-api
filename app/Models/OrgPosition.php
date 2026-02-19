<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgPosition extends Model
{
    protected $fillable = [
        'org_company_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}
