<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active'])]
class InventorySupplier extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(InventoryPurchase::class, 'supplier_id');
    }
}