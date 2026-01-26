<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgPosition extends Model
{
    use HasFactory;

    protected $table = 'org_positions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];
}
