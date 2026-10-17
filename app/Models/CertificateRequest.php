<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'requested_by',
    'reviewed_by',
    'certificate_type',
    'reason',
    'status',
    'certificate_number',
    'school_name',
    'student_name',
    'admission_number',
    'review_comments',
    'reviewed_at',
    'issued_at',
    'revoked_at', 
    'revoked_by', 
    'revocation_reason'
])]
class CertificateRequest extends Model
{
    public const TYPES = ['bonafide', 'character', 'completion', 'transfer'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
