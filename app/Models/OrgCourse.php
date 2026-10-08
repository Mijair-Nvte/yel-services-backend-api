<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrgCourse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'org_courses';

    protected $fillable = [
        'uid',
        'org_company_id',
        'created_by',
        'title',
        'slug',
        'description',
        'cover_image_url',
        'preview_video_url',
        'is_free',
        'revenuecat_entitlement_id',
        'price',
        'status',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'price' => 'decimal:2',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['cover_url', 'preview_url'];

    /**
     * Relación con la compañía (Tenant) a la que pertenece el curso.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con el usuario que creó el curso.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con los módulos del curso (Siguiente entidad a crear).
     */
    public function modules(): HasMany
    {
        return $this->hasMany(OrgCourseModule::class, 'org_course_id');
    }

    /**
     * Accessor para la URL de la portada del curso
     */
    public function getCoverUrlAttribute()
    {
        $value = $this->attributes['cover_image_url'] ?? null;
        if (empty($value)) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('r2_public')->url($value);
    }

    /**
     * Accessor para la URL del video de preview del curso
     */
    public function getPreviewUrlAttribute()
    {
        $value = $this->attributes['preview_video_url'] ?? null;
        if (empty($value)) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('r2_public')->url($value);
    }
}
