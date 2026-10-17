<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Grade scale and the low-attendance threshold. Same band rules as before. */
class UpdateGradingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'attendance_threshold' => ['required', 'numeric', 'between:0,100'],
            'grade_settings' => ['required', 'array:bands'],
            'grade_settings.bands' => ['required', 'array', 'min:2', 'max:12'],
            'grade_settings.bands.*' => ['required', 'array:label,minimum_percentage'],
            'grade_settings.bands.*.label' => ['required', 'string', 'max:10', 'distinct:ignore_case'],
            'grade_settings.bands.*.minimum_percentage' => ['required', 'numeric', 'between:0,100', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    $validator->errors()->has('grade_settings.bands')
                    || collect($validator->errors()->keys())
                        ->contains(fn (string $key): bool => str_starts_with($key, 'grade_settings.bands.'))
                ) {
                    return;
                }

                $bands = $this->input('grade_settings.bands', []);
                $minimums = collect($bands)
                    ->pluck('minimum_percentage')
                    ->filter(fn ($value): bool => is_numeric($value))
                    ->map(fn ($value): float => (float) $value);

                if ($minimums->count() === count($bands) && ! $minimums->contains(0.0)) {
                    $validator->errors()->add('grade_settings.bands', 'At least one grade band must start at 0 percent.');
                }

                if ($minimums->count() === count($bands) && $minimums->sortDesc()->values()->all() !== $minimums->values()->all()) {
                    $validator->errors()->add('grade_settings.bands', 'Grade bands must be ordered from the highest minimum percentage to the lowest.');
                }
            },
        ];
    }
}