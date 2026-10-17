<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('students.promote') ?? false)) {
            return false;
        }

        $decisions = $this->input('decisions', []);
        $hasOverride = is_array($decisions) && collect($decisions)
            ->contains(fn ($decision): bool => is_array($decision)
                && in_array($decision['outcome'] ?? null, ['repeated', 'graduated'], true));

        return ! $hasOverride || ($this->user()?->can('students.promote.override') ?? false);
    }

    public function rules(): array
    {
        return [
            'source_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'target_academic_year_id' => ['required', 'integer', 'different:source_academic_year_id', 'exists:academic_years,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'decisions' => ['required', 'array', 'min:1', 'max:100'],
            'decisions.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'decisions.*.outcome' => ['required', Rule::in(['promoted', 'repeated', 'graduated'])],
            'decisions.*.reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (ValidatorInstance $validator): void {
                $decisions = $this->input('decisions', []);
                if (! is_array($decisions)) {
                    return;
                }

                $needsPlacement = false;

                foreach ($decisions as $index => $decision) {
                    if (! is_array($decision)) {
                        continue;
                    }

                    $needsPlacement = $needsPlacement || in_array(
                        $decision['outcome'] ?? null,
                        ['promoted', 'repeated'],
                        true,
                    );

                    if (
                        in_array($decision['outcome'] ?? null, ['repeated', 'graduated'], true)
                        && blank($decision['reason'] ?? null)
                    ) {
                        $validator->errors()->add(
                            "decisions.{$index}.reason",
                            'Provide a reason for this promotion override.',
                        );
                    }
                }

                if ($needsPlacement && blank($this->input('class_id'))) {
                    $validator->errors()->add('class_id', 'Select a target class for students continuing their studies.');
                }

                if ($needsPlacement && blank($this->input('section_id'))) {
                    $validator->errors()->add('section_id', 'Select a target section for students continuing their studies.');
                }
            },
        ];
    }
}
