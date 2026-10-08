<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrgCourseLesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'org_course_lessons';

    protected $fillable = [
        'uid',
        'org_course_module_id',
        'title',
        'description',
        'video_path',
        'duration_seconds',
        'is_free_preview',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
        'is_active' => 'boolean',
        'duration_seconds' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['video_url'];

    /**
     * Relación con el módulo al que pertenece esta lección.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(OrgCourseModule::class, 'org_course_module_id');
    }

    /**
     * Accessor para la URL pública directa del video de la lección
     */
    public function getVideoUrlAttribute()
    {
        $path = $this->attributes['video_path'] ?? null;

        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Usamos el disco público r2_public, igual que las portadas e imágenes de propiedades
        return Storage::disk('r2_public')->url($path);
    }
}
