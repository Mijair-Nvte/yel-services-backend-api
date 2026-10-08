<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgCourseModule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'org_course_modules';

    protected $fillable = [
        'uid',
        'org_course_id',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relación con el curso al que pertenece este módulo.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(OrgCourse::class, 'org_course_id');
    }

    /**
     * Relación con las lecciones dentro de este módulo (Siguiente entidad).
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(OrgCourseLesson::class, 'org_course_module_id');
    }
}