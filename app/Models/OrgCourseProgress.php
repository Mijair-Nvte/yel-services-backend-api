<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgCourseProgress extends Model
{
    use HasFactory;

    protected $table = 'org_course_progress';

    protected $fillable = [
        'user_id',
        'org_course_lesson_id',
        'status',
        'watch_time_seconds',
        'completed_at',
    ];

    protected $casts = [
        'watch_time_seconds' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que está viendo la lección.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con la lección específica que se está viendo.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(OrgCourseLesson::class, 'org_course_lesson_id');
    }
}