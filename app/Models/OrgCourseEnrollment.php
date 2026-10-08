<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgCourseEnrollment extends Model
{
    use HasFactory;

    protected $table = 'org_course_enrollments';

    protected $fillable = [
        'org_company_id',
        'user_id',
        'org_course_id',
        'source',
        'status',
        'enrolled_at',
        'expires_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relación con la compañía.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    /**
     * Relación con el usuario inscrito.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el curso al que se inscribió.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(OrgCourse::class, 'org_course_id');
    }
}