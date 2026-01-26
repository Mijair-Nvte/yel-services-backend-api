<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $fillable = [
        'uid',
        'title',
        'description',
        'file_name',
        'file_url',
        'mime_type',
        'file_size',
        'storage_service',
        'uploaded_by',
        'folder_id',
    ];

    protected static function booted()
    {
        static::creating(function ($doc) {
            $doc->uid ??= 'doc_'.Str::ulid();
        });
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
