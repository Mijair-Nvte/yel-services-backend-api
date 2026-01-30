<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeLevel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    public function notices()
    {
        return $this->hasMany(OrgCompanyNotice::class);
    }
}
