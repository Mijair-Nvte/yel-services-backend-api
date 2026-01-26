<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgCompanyUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_company_id',
        'role',
        'is_active',
    ];

    /* =========================
     |  Relaciones
     ========================= */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }
}
