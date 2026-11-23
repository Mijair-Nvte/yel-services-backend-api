<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgProjectTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'org_project_id',
        'name',
        'description',
        'department',
        'platform',
        'task_type',
        'phase',       
        'is_milestone',
        'start_date',
        'end_date',
        'status',
        'assigned_to',
        'org_event_id',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_milestone' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::uuid();
            }
        });
    }

    // Relaciones
    public function project()
    {
        return $this->belongsTo(OrgProject::class, 'org_project_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function event()
    {
        return $this->belongsTo(OrgEvent::class, 'org_event_id');
    }
}