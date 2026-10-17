<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requirement_id', 'student_id', 'marked_by', 'notes'])]
class ClassSupplyExternalFulfilment extends Model
{
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ClassSupplyRequirement::class, 'requirement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}