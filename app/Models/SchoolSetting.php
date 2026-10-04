<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'singleton_key',
    'school_name',
    'motto',
    'address',
    'phone',
    'email',
    'website',
    'logo_path',
    'attendance_threshold',
    'grade_settings',
    'fee_settings',
    'notification_settings',
])]
class SchoolSetting extends Model
{
    protected function casts(): array
    {
        return [
            'attendance_threshold' => 'decimal:2',
            'grade_settings' => 'array',
            'fee_settings' => 'array',
            'notification_settings' => 'array',
        ];
    }
}
