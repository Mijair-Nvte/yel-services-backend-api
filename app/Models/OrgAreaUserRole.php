<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgAreaUserRole extends Model
{
    protected $fillable = [
        'user_id',
        'org_area_id',
        'position_title',
        'org_role_id',
        'is_primary',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(OrgArea::class, 'org_area_id');
    }

   public function position()
{
    return $this->belongsTo(OrgPosition::class, 'org_role_id');
}

}
