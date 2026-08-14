<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrgFeedback extends Model
{
    use SoftDeletes;

    protected $table = 'org_feedbacks';
    
    protected $fillable = [
        'uid', 'org_company_id', 'user_id', 'type','source', 'status', 'title', 'description', 'document_id'
    ];

    // Generar el UID automáticamente al crear
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = 'fbk_' . strtoupper(Str::random(10)); 
            }
        });
    }

    // Relaciones
    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}