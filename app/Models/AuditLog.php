<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_id',
    'actor_name',
    'academic_year_id',
    'action',
    'subject_type',
    'subject_id',
    'subject_name',
    'summary',
    'changed_fields',
    'context',
    'ip_address',
    'route_name',
    'source_type',
    'source_id',
    'occurred_at',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
