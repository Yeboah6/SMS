<?php

namespace App\Services;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\DB;

class SchoolSettingsService
{
    /**
     * @param  array{school_name: string, motto?: string|null, address?: string|null, phone?: string|null, email?: string|null, website?: string|null, attendance_threshold: numeric-string|float|int}  $data
     */
    public function update(array $data): SchoolSetting
    {
        return DB::transaction(fn (): SchoolSetting => SchoolSetting::query()->updateOrCreate(
            ['singleton_key' => 'primary'],
            $data,
        ));
    }
}
