<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admission_id', 'from_status', 'to_status', 'changed_by', 'notes'])]
class AdmissionStatusHistory extends Model
{
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}