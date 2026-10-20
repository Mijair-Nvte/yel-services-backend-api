<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgCourseResource extends Model
{
    protected $fillable = [
        'org_company_id',
        'resourceable_id',
        'resourceable_type',
        'title',
        'file_name',
        'file_url', // Aquí guardaremos solo la ruta relativa
        'mime_type',
        'file_size',
        'sort_order',
        'is_active',
    ];

    // Agregamos el atributo virtual al array del JSON
    protected $appends = ['public_url'];

    protected static function booted()
    {
        static::creating(function ($resource) {
            if (empty($resource->uid)) {
                $resource->uid = 'res_'.Str::ulid();
            }
        });
    }

    public function resourceable()
    {
        return $this->morphTo();
    }

    /**
     * Accessor para resolver la URL pública del archivo
     */
    public function getPublicUrlAttribute()
    {
        $path = $this->attributes['file_url'] ?? null;

        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('r2_public')->url($path);
    }
}
