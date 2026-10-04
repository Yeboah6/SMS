<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class Designation extends Model
{
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
