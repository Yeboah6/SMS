<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'class_id', 'term_id', 'item_id', 'quantity_per_student', 'is_required', 'notes', 'created_by'])]
class ClassSupplyRequirement extends Model
{
    protected function casts(): array
    {
        return ['quantity_per_student' => 'integer', 'is_required' => 'boolean'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    public function externalFulfilments(): HasMany
    {
        return $this->hasMany(ClassSupplyExternalFulfilment::class, 'requirement_id');
    }
}