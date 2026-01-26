<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Folder extends Model
{
    protected $fillable = [
        'uid',
        'name',
        'parent_id',
        'folderable_id',
        'folderable_type',
        'created_by',
        'order',
    ];

    protected static function booted()
    {
        static::creating(function ($folder) {
            $folder->uid ??= 'fld_'.Str::ulid();
        });
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
}
