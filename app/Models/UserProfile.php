<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class UserProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'country',
        'state',
        'city',
        'avatar',
        'timezone',
        'language',
    ];

    /**
     * 👇 Esto hace que avatar_url se agregue automáticamente al JSON
     */
    protected $appends = ['avatar_url'];

    /**
     * Relación con User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 🎯 Accessor PRO
     * Genera automáticamente la URL pública del avatar
     */
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }

        return asset('storage/' . $this->avatar);
    }
}
