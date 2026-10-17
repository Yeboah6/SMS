<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_id',
    'uploaded_by',
    'category',
    'original_name',
    'storage_path',
    'mime_type',
    'size_bytes',
])]
class AdmissionDocument extends Model
{
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
