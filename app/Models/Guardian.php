<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'email', 'occupation', 'address'])]
class Guardian extends Model
{
    use SoftDeletes;

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot(['relationship', 'is_primary', 'lives_with_student'])
            ->withTimestamps();
    }
}
