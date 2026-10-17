<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $permission = match ($this->input('status')) {
            'application_received', 'under_review' => 'admissions.review',
            'approved', 'rejected' => 'admissions.approve',
            'enrolled' => 'admissions.enrol',
            default => null,
        };

        if ($permission === null || ! ($actor?->can($permission) ?? false)) {
            return false;
        }

        if ($this->boolean('assign_fees') && ! ($actor?->can('invoices.create') ?? false)) {
            return false;
        }

        return ! $this->boolean('first_payment') || ($actor?->can('payments.create') ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'application_received',
                'under_review',
                'approved',
                'rejected',
                'enrolled',
            ])],
            'review_notes' => [
                Rule::requiredIf($this->input('status') === 'rejected'),
                'nullable',
                'string',
                'max:5000',
            ],
            'academic_year_id' => ['required_if:status,enrolled', 'nullable', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required_if:status,enrolled', 'nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['required_if:status,enrolled', 'nullable', 'integer', 'exists:sections,id'],
            'enrolment_date' => ['required_if:status,enrolled', 'nullable', 'date'],
            'assign_fees' => ['sometimes', 'boolean'],
            'first_payment' => ['sometimes', 'boolean'],
            'first_payment_amount' => [Rule::requiredIf($this->boolean('first_payment')), 'nullable', 'numeric', 'gt:0'],
            'first_payment_method' => [Rule::requiredIf($this->boolean('first_payment')), 'nullable', Rule::in(['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'])],
            'first_payment_reference' => ['nullable', 'string', 'max:100'],
            'first_payment_date' => [Rule::requiredIf($this->boolean('first_payment')), 'nullable', 'date', 'before_or_equal:today'],
            'first_payment_notes' => ['nullable', 'string', 'max:2000'],
            'create_student_account' => ['sometimes', 'boolean'],
            'student_email' => [Rule::requiredIf($this->boolean('create_student_account')), 'nullable', 'email', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->boolean('first_payment') && ! $this->boolean('assign_fees')) {
                    $validator->errors()->add('first_payment', 'Create the student invoice before recording a first payment.');
                }
            },
        ];
    }
}
