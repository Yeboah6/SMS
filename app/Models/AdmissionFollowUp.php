<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_id',
    'created_by',
    'completed_by',
    'follow_up_at',
    'method',
    'notes',
    'status',
    'outcome',
    'completed_at',
])]
class AdmissionFollowUp extends Model
{
    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
