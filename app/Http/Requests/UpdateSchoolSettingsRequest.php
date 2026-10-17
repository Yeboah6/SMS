<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'attendance_threshold' => ['required', 'numeric', 'between:0,100'],
            'grade_settings' => ['sometimes', 'array:bands'],
            'grade_settings.bands' => ['required_with:grade_settings', 'array', 'min:2', 'max:12'],
            'grade_settings.bands.*' => ['required', 'array:label,minimum_percentage'],
            'grade_settings.bands.*.label' => ['required', 'string', 'max:10', 'distinct:ignore_case'],
            'grade_settings.bands.*.minimum_percentage' => ['required', 'numeric', 'between:0,100', 'distinct'],
            'fee_settings' => ['sometimes', 'array:currency_code,default_due_days'],
            'fee_settings.currency_code' => ['required_with:fee_settings', 'string', 'regex:/^[A-Z]{3}$/'],
            'fee_settings.default_due_days' => ['required_with:fee_settings', 'integer', 'between:1,365'],
            'notification_settings' => ['sometimes', 'array:database_notices,email_notices'],
            'notification_settings.database_notices' => ['required_with:notification_settings', 'boolean'],
            'notification_settings.email_notices' => ['required_with:notification_settings', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    ! $this->exists('grade_settings.bands')
                    || $validator->errors()->has('grade_settings.bands')
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
                    $validator->errors()->add(
                        'grade_settings.bands',
                        'At least one grade band must start at 0 percent.',
                    );
                }

                if ($minimums->count() === count($bands) && $minimums->sortDesc()->values()->all() !== $minimums->values()->all()) {
                    $validator->errors()->add(
                        'grade_settings.bands',
                        'Grade bands must be ordered from the highest minimum percentage to the lowest.',
                    );
                }
            },
        ];
    }
}
